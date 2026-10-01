(function () {
  'use strict';

  var cfg = window.AGENT;
  if (!cfg) return;

  var chat = document.getElementById('chat');
  var feed = document.getElementById('feed');
  var arts = document.getElementById('arts');
  var planBox = document.getElementById('plan');
  var planCard = document.getElementById('plan-card');
  var runBtn = document.getElementById('run-btn');
  var thinking = document.getElementById('thinking');
  var hint = document.getElementById('hint');
  var busy = false;

  var STATUS_COLORS = { draft: 'gray', running: 'blue', done: 'green', failed: 'red', cancelled: 'amber' };
  var notified = cfg.finished;

  // Мост десктоп-приложения есть только внутри Electron.
  if (window.desktop && window.desktop.isDesktop) {
    var folderBtn = document.getElementById('open-workspace');
    if (folderBtn) {
      folderBtn.style.display = '';
      folderBtn.addEventListener('click', function () { window.desktop.openWorkspace(); });
    }
  }

  function csrf() {
    return document.querySelector('meta[name="csrf-token"]').content;
  }

  function seen(key) {
    return new Set(Array.prototype.slice.call(document.querySelectorAll('[data-id]' + key)));
  }

  function renderMessages(messages) {
    var have = seen('[data-id]').value;
    have.forEach(function (id) { have.add(id); });

    messages.forEach(function (m) {
      if (have.has(String(m.id))) return;

      var node = document.createElement('div');
      node.className = 'msg ' + m.role;
      node.setAttribute('data-id', m.id);

      var who = document.createElement('div');
      who.className = 'who';
      who.textContent = m.role === 'user' ? 'вы'
        : m.role === 'assistant' ? 'агент'
        : m.role === 'tool' ? 'инструмент · ' + (m.tool || '?') : 'система';

      var body = document.createElement('div');
      body.className = 'body';
      body.textContent = m.content || '';

      node.appendChild(who);
      node.appendChild(body);
      chat.insertBefore(node, thinking);
      node.scrollIntoView({ block: 'end' });
    });
  }

  function renderSteps(steps) {
    var have = new Set(Array.prototype.map.call(feed.querySelectorAll('.feed-item'), function (n) { return n.dataset.id; }));
    if (steps.length === 0) return;

    var empty = feed.querySelector('.muted.small');
    if (empty) empty.remove();

    steps.forEach(function (s) {
      if (have.has(String(s.id))) {
        var existing = feed.querySelector('[data-id="' + s.id + '"]');
        if (existing) existing.className = 'feed-item ' + s.status;
        return;
      }

      var node = document.createElement('div');
      node.className = 'feed-item ' + s.status;
      node.setAttribute('data-id', s.id);
      node.innerHTML = '<div class="h"><span></span><span class="small muted mono"></span></div><div class="o"></div>';
      node.querySelector('.h span').textContent = s.title || s.tool;
      node.querySelector('.h .mono').textContent = s.duration ? s.duration + ' мс' : '';
      node.querySelector('.o').textContent = s.output || '';
      feed.appendChild(node);
    });

    document.getElementById('steps-count').textContent = steps.length;
    feed.lastElementChild.scrollIntoView({ block: 'nearest' });
  }

  function renderArtifacts(artifacts) {
    if (artifacts.length === 0) return;

    var empty = arts.querySelector('.muted.small');
    if (empty) empty.remove();

    var icons = { document: '📄', code: '💻', chart: '📈', data: '🗂️', html: '🌐' };
    var have = new Set(Array.prototype.map.call(arts.querySelectorAll('.art'), function (n) { return n.dataset.id; }));

    artifacts.forEach(function (a) {
      if (have.has(String(a.id))) return;

      var node = document.createElement('div');
      node.className = 'art';
      node.setAttribute('data-id', a.id);
      node.innerHTML = '<span class="ic"></span><div style="min-width:0;flex:1"><div class="nm"></div><div class="mt"></div></div>'
        + '<div class="row-actions"><a class="btn btn-sm" target="_blank">Открыть</a><a class="btn btn-sm">⬇</a></div>';
      node.querySelector('.ic').textContent = icons[a.kind] || '📎';
      node.querySelector('.nm').textContent = a.name;
      node.querySelector('.mt').textContent = a.size;
      node.querySelectorAll('a')[0].href = a.url;
      node.querySelectorAll('a')[1].href = a.url + '/download';
      arts.appendChild(node);
    });

    document.getElementById('arts-count').textContent = artifacts.length;
  }

  function renderPlan(plan) {
    if (!plan || plan.length === 0) return;

    planCard.style.display = '';
    planBox.innerHTML = plan.map(function (s) {
      var status = s.status || 'todo';
      return '<div class="plan-step ' + status + '"><span class="mark">' + (status === 'done' ? '✓' : '') + '</span><span></span></div>';
    }).join('');
    Array.prototype.forEach.call(planBox.children, function (node, i) {
      node.lastElementChild.textContent = plan[i].title;
    });
  }

  function renderState(data) {
    renderMessages(data.messages);
    renderSteps(data.steps);
    renderArtifacts(data.artifacts);
    renderPlan(data.plan);

    document.getElementById('iteration').textContent = data.iteration;
    document.getElementById('tokens').textContent = data.tokens;

    var badge = document.getElementById('status-badge');
    badge.textContent = data.status_label;
    badge.className = 'badge b-' + (STATUS_COLORS[data.status] || 'gray');

    if (data.error) {
      hint.textContent = 'Ошибка: ' + data.error;
      hint.style.color = 'var(--red)';
    }

    if (!data.has_more && busy) {
      thinking.style.display = 'none';
      runBtn.disabled = true;
      hint.textContent = hint.textContent || cfg.i18n.done;
      busy = false;

      if (!notified && (data.status === 'done' || data.status === 'failed')) {
        notified = true;
        if (window.desktop && window.desktop.isDesktop) {
          window.desktop.notify(
            data.status === 'done' ? 'Задача выполнена' : 'Задача завершилась с ошибкой',
            document.title
          );
        }
      }
    }

    return data;
  }

  function tick() {
    return fetch(cfg.tickUrl, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
      body: ''
    })
      .then(function (r) { return r.json(); })
      .then(renderState)
      .then(function (data) {
        if (data.has_more) {
          return new Promise(function (resolve) { setTimeout(resolve, 250); }).then(tick);
        }
        return data;
      });
  }

  runBtn.addEventListener('click', function () {
    if (busy) return;

    busy = true;
    runBtn.disabled = true;
    hint.textContent = cfg.i18n.thinking;
    hint.style.color = '';
    thinking.style.display = '';
    thinking.scrollIntoView({ block: 'end' });

    tick().catch(function (e) {
      hint.textContent = 'Сбой связи: ' + e.message;
      hint.style.color = 'var(--red)';
      busy = false;
      runBtn.disabled = false;
    });
  });

  if (!cfg.finished) {
    // Задача ждёт запуска — показываем подсказку.
    hint.textContent = 'Нажмите «Запустить», чтобы агент взялся за работу.';
  }

  var composer = document.getElementById('composer');
  var textarea = composer.querySelector('textarea');
  textarea.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      composer.submit();
    }
  });

  document.querySelectorAll('[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
    });
  });
})();