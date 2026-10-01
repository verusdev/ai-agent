'use strict';

/**
 * Запуск Electron-приложения.
 *
 * Нужен потому, что в некоторых окружениях (CI, терминалы, IDE) выставлена
 * переменная ELECTRON_RUN_AS_NODE — тогда Electron стартует как обычный Node и
 * не создаёт окно. Здесь мы её убираем и только затем запускаем main.js.
 */

const { spawn } = require('child_process');
const path = require('path');

const electronBinary = require('electron');
const args = [path.join(__dirname, 'main.js'), ...process.argv.slice(2)];

const env = { ...process.env };
delete env.ELECTRON_RUN_AS_NODE;

const child = spawn(electronBinary, args, { stdio: 'inherit', env });

child.on('error', (error) => {
  console.error('Не удалось запустить Electron:', error.message);
  process.exit(1);
});

child.on('exit', (code) => process.exit(code ?? 0));