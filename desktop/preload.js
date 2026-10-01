'use strict';

/**
 * Мост между Laravel-интерфейсом и возможностями рабочего стола.
 * В браузере объект window.desktop отсутствует — код проверяет это через if.
 */

const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('desktop', {
  isDesktop: true,

  info: () => ipcRenderer.invoke('desktop:info'),

  /** Нативное уведомление о завершении задачи. */
  notify: (title, body) => ipcRenderer.send('desktop:notify', { title, body }),

  /** Открыть папку или файл в проводнике / редакторе. */
  openPath: (target) => ipcRenderer.send('desktop:open-path', target),

  /** Открыть папку с артефактами задач. */
  openWorkspace: () => ipcRenderer.send('desktop:open-workspace'),

  /** Перейти к разделу приложения из нативного меню. */
  navigate: (pathname) => ipcRenderer.send('desktop:navigate', pathname),
});