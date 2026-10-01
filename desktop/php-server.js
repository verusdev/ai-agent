'use strict';

/**
 * Менеджер встроенного PHP-сервера Laravel.
 *
 * Приложение само поднимает `php artisan serve` на свободном порту,
 * ждёт готовности health-эндпоинта и гасит процесс при выходе.
 */

const { spawn, execFileSync } = require('child_process');
const net = require('net');
const http = require('http');
const path = require('path');
const fs = require('fs');

const DEFAULT_PORT = 8124;
const BOOT_TIMEOUT_MS = 30_000;
const POLL_INTERVAL_MS = 350;

class PhpServer {
  /**
   * @param {object} options
   * @param {string} options.root   корень Laravel-приложения
   * @param {string} [options.php]  путь к интерпретатору PHP
   */
  constructor(options) {
    this.root = options.root;
    this.php = options.php || this.detectPhp();
    this.port = Number(process.env.AGENT_PORT) || DEFAULT_PORT;
    this.process = null;
    this.output = [];
  }

  /**
   * Ищет настоящий php.exe.
   *
   * На Windows PHP часто доступен не как .exe, а как .cmd-шим (ServBay, XAMPP,
   * Scoop), и Node не умеет запускать такие файлы без оболочки. Поэтому спрашиваем
   * у самого PHP, где он находится, и используем реальный путь.
   */
  detectPhp() {
    const configured = process.env.AI_AGENT_PHP || this.readPhpHint();

    if (configured && fs.existsSync(configured)) {
      return configured;
    }

    if (process.platform !== 'win32') {
      return 'php';
    }

    try {
      const real = execFileSync('cmd.exe', ['/c', 'php', '-r', 'echo PHP_BINARY;'], {
        encoding: 'utf8',
        windowsHide: true,
        stdio: ['ignore', 'pipe', 'ignore'],
      }).trim();

      if (real && fs.existsSync(real)) {
        return real;
      }
    } catch {
      // дальше пробуем обычный путь
    }

    return 'php';
  }

  /** Подсказка из файла desktop/php-path.txt — для нестандартных установок. */
  readPhpHint() {
    try {
      const hint = fs.readFileSync(path.join(__dirname, 'php-path.txt'), 'utf8').trim();
      return hint || null;
    } catch {
      return null;
    }
  }

  get baseUrl() {
    return `http://127.0.0.1:${this.port}`;
  }

  /** Поднимает сервер и возвращает его базовый URL. */
  async start() {
    if (!(await this.isPortFree(this.port))) {
      this.port = await this.findFreePort(this.port + 1);
    }

    this.process = spawn(
      this.php,
      ['artisan', 'serve', '--host=127.0.0.1', `--port=${this.port}`],
      {
        cwd: this.root,
        windowsHide: true,
        env: { ...process.env, NO_COLOR: '1' },
      }
    );

    this.process.stdout.on('data', (chunk) => this.collect(chunk));
    this.process.stderr.on('data', (chunk) => this.collect(chunk));
    this.process.on('error', (error) => {
      this.process = null;
      this.bootError = error.code === 'ENOENT'
        ? `Не найден интерпретатор PHP (${this.php}). Укажите полный путь в файле desktop/php-path.txt.`
        : error.message;
    });
    this.process.on('exit', (code) => {
      this.process = null;
      if (code !== 0 && code !== null) {
        console.error('[php] процесс завершился с кодом', code);
      }
    });

    await this.waitUntilReady();

    return this.baseUrl;
  }

  stop() {
    if (!this.process) return;

    try {
      // На Windows artisan serve порождает дочерние процессы — гасим дерево.
      if (process.platform === 'win32') {
        spawn('taskkill', ['/pid', String(this.process.pid), '/T', '/F'], { windowsHide: true });
      } else {
        this.process.kill('SIGTERM');
      }
    } catch (error) {
      console.error('[php] не удалось остановить сервер:', error.message);
    }

    this.process = null;
  }

  collect(chunk) {
    const text = chunk.toString();
    this.output.push(text);
    if (this.output.length > 200) this.output.shift();
    console.log('[php]', text.trim());
  }

  waitUntilReady() {
    const deadline = Date.now() + BOOT_TIMEOUT_MS;

    return new Promise((resolve, reject) => {
      const poll = () => {
        if (this.bootError) {
          reject(new Error(this.bootError));
          return;
        }

        if (this.output.some((line) => line.includes('Whoops') || line.includes('Fatal error'))) {
          reject(new Error('PHP-сервер не стартовал:\n' + this.output.join('')));
          return;
        }

        this.ping()
          .then((ok) => (ok ? resolve(this.baseUrl) : retry()))
          .catch(retry);
      };

      const retry = () => {
        if (Date.now() > deadline) {
          reject(new Error('PHP-сервер не ответил за ' + BOOT_TIMEOUT_MS / 1000 + ' с'));
          return;
        }
        setTimeout(poll, POLL_INTERVAL_MS);
      };

      poll();
    });
  }

  ping() {
    return new Promise((resolve) => {
      const request = http.get(`${this.baseUrl}/up`, (response) => {
        response.resume();
        resolve(response.statusCode < 500);
      });

      request.on('error', () => resolve(false));
      request.setTimeout(2000, () => {
        request.destroy();
        resolve(false);
      });
    });
  }

  isPortFree(port) {
    return new Promise((resolve) => {
      const socket = net.createConnection({ host: '127.0.0.1', port });
      socket.once('connect', () => {
        socket.destroy();
        resolve(false);
      });
      socket.once('error', () => resolve(true));
      socket.setTimeout(500, () => {
        socket.destroy();
        resolve(true);
      });
    });
  }

  async findFreePort(start) {
    for (let port = start; port < start + 20; port += 1) {
      if (await this.isPortFree(port)) return port;
    }

    throw new Error('Не нашлось свободного порта для встроенного сервера');
  }
}

module.exports = { PhpServer, laravelRoot: path.resolve(__dirname, '..') };