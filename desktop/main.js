'use strict';

/**
 * Главный процесс Electron-обёртки.
 *
 * Окно приложения показывает обычный интерфейс Laravel, а встроенный PHP-сервер
 * поднимается автоматически. Пользователю не нужно ничего запускать руками.
 */

const { app, BrowserWindow, Menu, Tray, ipcMain, shell, dialog, nativeImage, Notification } = require('electron');
const path = require('path');
const fs = require('fs');
const os = require('os');
const { PhpServer } = require('./php-server');

const ROOT = path.resolve(__dirname, '..');
const ICON = path.join(__dirname, 'build', 'icon.png');

let mainWindow = null;
let tray = null;
let server = null;
let windowState = { width: 1320, height: 900 };

// Второй экземпляр не нужен: фокусируем существующее окно.
if (!app.requestSingleInstanceLock()) {
  app.quit();
}

app.on('second-instance', () => showWindow());

function loadWindowState() {
  const file = path.join(app.getPath('userData'), 'window-state.json');

  try {
    const saved = JSON.parse(fs.readFileSync(file, 'utf8'));
    windowState = { ...windowState, ...saved };
  } catch {
    // первый запуск — используем значения по умолчанию
  }
}

function saveWindowState() {
  if (!mainWindow) return;

  const file = path.join(app.getPath('userData'), 'window-state.json');
  const bounds = mainWindow.getNormalBounds();

  fs.writeFileSync(file, JSON.stringify(bounds));
}

function appIcon() {
  return fs.existsSync(ICON) ? nativeImage.createFromPath(ICON) : undefined;
}

function showWindow() {
  if (!mainWindow) return createWindow();
  if (mainWindow.isMinimized()) mainWindow.restore();
  mainWindow.show();
  mainWindow.focus();
}

function createWindow(url) {
  mainWindow = new BrowserWindow({
    ...windowState,
    minWidth: 960,
    minHeight: 640,
    show: false,
    backgroundColor: '#0f172a',
    title: 'AI Агент',
    icon: appIcon(),
    autoHideMenuBar: false,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
    },
  });

  mainWindow.once('ready-to-show', () => mainWindow.show());
  mainWindow.on('close', saveWindowState);
  mainWindow.on('closed', () => {
    mainWindow = null;
  });

  // Внешние ссылки открываем в системном браузере, а не внутри окна.
  mainWindow.webContents.setWindowOpenHandler(({ url: target }) => {
    if (target.startsWith('http')) shell.openExternal(target);
    return { action: 'deny' };
  });

  mainWindow.webContents.on('did-fail-load', (event, code) => {
    if (code === -3) return; // ERR_ABORTED — обычное прерывание при перезагрузке
    dialog.showErrorBox('Ошибка загрузки', `Не удалось открыть интерфейс (код ${code}).`);
  });

  mainWindow.loadURL(url);
}

function buildMenu() {
  const template = [
    {
      label: 'Задача',
      submenu: [
        {
          label: 'Новая задача',
          accelerator: 'CmdOrCtrl+N',
          click: () => navigate('/agents'),
        },
        {
          label: 'Модели и ключи',
          accelerator: 'CmdOrCtrl+K',
          click: () => navigate('/providers'),
        },
        { type: 'separator' },
        { label: 'Перезапустить сервер', click: () => restartServer() },
      ],
    },
    {
      label: 'Вид',
      submenu: [
        { role: 'reload', label: 'Обновить' },
        { role: 'togglefullscreen', label: 'Полный экран' },
        { type: 'separator' },
        { role: 'resetZoom', label: 'Обычный масштаб' },
        { role: 'zoomIn', label: 'Увеличить' },
        { role: 'zoomOut', label: 'Уменьшить' },
        { type: 'separator' },
        { role: 'toggleDevTools', label: 'Инструменты разработчика' },
      ],
    },
    {
      label: 'Справка',
      submenu: [
        {
          label: 'Открыть папку артефактов',
          click: () => openWorkspaceFolder(),
        },
        {
          label: 'Открыть БД SQLite',
          click: () => openPath(path.join(ROOT, 'database', 'database.sqlite')),
        },
        { type: 'separator' },
        {
          label: 'О программе',
          click: () => {
            dialog.showMessageBox(mainWindow, {
              type: 'info',
              title: 'AI Агент',
              message: 'AI Агент 1.0',
              detail:
                `ИИ-агент на бесплатных моделях.\n\nPHP: ${server ? server.php : 'php'}\n` +
                `Сервер: ${server ? server.baseUrl : '—'}\n` +
                `Данные: ${path.join(ROOT, 'database')}`,
              buttons: ['Ок'],
            });
          },
        },
        { type: 'separator' },
        { role: 'quit', label: 'Выход' },
      ],
    },
  ];

  Menu.setApplicationMenu(Menu.buildFromTemplate(template));
}

function buildTray() {
  if (!fs.existsSync(ICON)) return;

  try {
    tray = new Tray(appIcon());
    tray.setToolTip('AI Агент');
    tray.setContextMenu(
      Menu.buildFromTemplate([
        { label: 'Открыть', click: () => showWindow() },
        { label: 'Новая задача', click: () => { showWindow(); navigate('/agents'); } },
        { type: 'separator' },
        { label: 'Выход', click: () => app.quit() },
      ])
    );
    tray.on('double-click', () => showWindow());
  } catch (error) {
    console.error('[tray]', error.message);
  }
}

function navigate(pathname) {
  if (!mainWindow || !server) return;

  const target = server.baseUrl + pathname;
  if (mainWindow.webContents.getURL().startsWith(server.baseUrl)) {
    mainWindow.webContents.executeJavaScript(`window.location.href = ${JSON.stringify(target)}`);
  } else {
    mainWindow.loadURL(target);
  }
}

function openPath(target) {
  if (!target || !fs.existsSync(target)) {
    dialog.showMessageBox(mainWindow, {
      type: 'warning',
      title: 'AI Агент',
      message: 'Папка пока не создана',
      detail: target,
      buttons: ['Ок'],
    });
    return;
  }

  shell.openPath(target);
}

function openWorkspaceFolder() {
  const dir = path.join(ROOT, 'storage', 'app', 'private', 'agents');
  openPath(dir);
}

async function restartServer() {
  if (!server) return;

  await waitForServerStop(server);
  server = new PhpServer({ root: ROOT });
  await bootServer();
}

function waitForServerStop(instance) {
  return new Promise((resolve) => {
    if (!instance.process) return resolve();
    instance.process.once('exit', () => setTimeout(resolve, 300));
    instance.stop();
    setTimeout(resolve, 1500);
  });
}

async function bootServer() {
  try {
    const url = await server.start();
    buildMenu();
    if (mainWindow) mainWindow.loadURL(url);
    else createWindow(url);
    return url;
  } catch (error) {
    dialog.showErrorBox(
      'Не удалось запустить сервер',
      `${error.message}\n\nПроверьте, что PHP доступен в PATH и зависимости установлены (composer install).\n` +
        `Каталог приложения: ${ROOT}`
    );
    app.quit();
  }
}

/* ---------------- IPC из окна ---------------- */

ipcMain.handle('desktop:info', () => ({
  version: app.getVersion(),
  platform: process.platform,
  serverUrl: server ? server.baseUrl : null,
  root: ROOT,
}));

ipcMain.handle('desktop:notify', (_event, { title, body }) => {
  if (Notification.isSupported()) {
    new Notification({ title: title || 'AI Агент', body: body || '' }).show();
  }
});

ipcMain.on('desktop:open-path', (_event, target) => openPath(target));
ipcMain.on('desktop:open-workspace', () => openWorkspaceFolder());
ipcMain.on('desktop:navigate', (_event, pathname) => navigate(pathname));

/* ---------------- Жизненный цикл ---------------- */

app.whenReady().then(async () => {
  loadWindowState();
  server = new PhpServer({ root: ROOT });
  await bootServer();
  buildTray();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow(server.baseUrl);
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});

app.on('before-quit', () => {
  if (server) server.stop();
  saveWindowState();
});

process.on('uncaughtException', (error) => {
  console.error('[main]', error);
  if (server) server.stop();
  app.quit();
});