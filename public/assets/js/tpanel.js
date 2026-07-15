(() => {
  const root = document.documentElement;
  const shell = document.querySelector('[data-shell]');
  const themeToggle = document.querySelector('[data-theme-toggle]');
  const menuToggle = document.querySelector('[data-menu-toggle]');
  const storedTheme = window.localStorage.getItem('tpanel.theme');

  if (storedTheme === 'light' || storedTheme === 'dark') {
    root.dataset.theme = storedTheme;
  }

  themeToggle?.addEventListener('click', () => {
    const nextTheme = root.dataset.theme === 'dark' ? 'light' : 'dark';
    root.dataset.theme = nextTheme;
    window.localStorage.setItem('tpanel.theme', nextTheme);
  });

  menuToggle?.addEventListener('click', () => {
    shell?.classList.toggle('is-menu-open');
  });

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }[char]));

  const alertButton = document.querySelector('[data-alerts-button]');
  const alertCount = document.querySelector('[data-alerts-count]');
  const alertDialog = document.querySelector('[data-alerts-dialog]');
  const alertDialogBody = document.querySelector('[data-alerts-dialog-body]');
  const parseNavigationAlerts = () => {
    try {
      return JSON.parse(document.getElementById('navigation-alerts-data')?.textContent || '{}');
    } catch {
      return { total: 0, warning: 0, critical: 0, pages: {}, alerts: [] };
    }
  };
  const alertState = parseNavigationAlerts();

  const summarizeAlerts = () => {
    const pages = {};
    let warning = 0;
    let critical = 0;

    (alertState.alerts || []).forEach((alert) => {
      const pageKey = alert.pageKey || 'overview';
      const severity = alert.severity === 'CRITICAL' ? 'CRITICAL' : 'WARNING';
      pages[pageKey] ||= { warning: 0, critical: 0, total: 0 };
      pages[pageKey].total += 1;

      if (severity === 'CRITICAL') {
        critical += 1;
        pages[pageKey].critical += 1;
      } else {
        warning += 1;
        pages[pageKey].warning += 1;
      }
    });

    alertState.pages = pages;
    alertState.warning = warning;
    alertState.critical = critical;
    alertState.total = warning + critical;
  };

  const updateNavigationBadges = () => {
    summarizeAlerts();

    if (alertCount) {
      alertCount.textContent = String(alertState.total || 0);
    }

    document.querySelectorAll('[data-nav-alerts-for]').forEach((badge) => {
      const pageKey = badge.getAttribute('data-nav-alerts-for') || '';
      const counts = alertState.pages?.[pageKey] || { warning: 0, critical: 0, total: 0 };
      const criticalNode = badge.querySelector('[data-nav-alert-critical]');
      const warningNode = badge.querySelector('[data-nav-alert-warning]');
      badge.hidden = counts.total === 0;

      if (criticalNode) {
        criticalNode.textContent = String(counts.critical);
        criticalNode.hidden = counts.critical === 0;
      }

      if (warningNode) {
        warningNode.textContent = String(counts.warning);
        warningNode.hidden = counts.warning === 0;
      }
    });
  };

  const renderAlertDialog = () => {
    if (!alertDialogBody) {
      return;
    }

    if (!alertState.alerts || alertState.alerts.length === 0) {
      alertDialogBody.innerHTML = '<div class="memory-status-ok"><strong>OK</strong><span>Nenhum alerta ativo nas páginas monitoradas.</span></div>';
      return;
    }

    alertDialogBody.innerHTML = alertState.alerts.map((alert) => `
      <a class="alert-dialog-item severity-left-${escapeHtml(alert.severity || 'WARNING')}" href="${escapeHtml(alert.href || '/')}" data-alert-link>
        <strong class="severity-badge severity-${escapeHtml(alert.severity || 'WARNING')}">${escapeHtml(alert.severity || 'WARNING')}</strong>
        <span>
          <b>${escapeHtml(alert.pageLabel || 'Painel')}</b>
          ${escapeHtml(alert.title || 'Alerta')}
        </span>
        <small>${escapeHtml(alert.detail || '')}</small>
      </a>
    `).join('');
  };

  const replacePageAlerts = (pageKey, pageLabel, href, reasons = []) => {
    alertState.alerts = (alertState.alerts || []).filter((alert) => alert.pageKey !== pageKey);
    alertState.alerts.push(...reasons.map((reason) => ({
      pageKey,
      pageLabel,
      href,
      severity: reason.severity === 'CRITICAL' ? 'CRITICAL' : 'WARNING',
      title: reason.label || 'Threshold ativo',
      detail: reason.value || '',
    })));
    updateNavigationBadges();
  };

  alertButton?.addEventListener('click', () => {
    renderAlertDialog();
    alertDialog?.showModal();
  });
  updateNavigationBadges();

  const cpuPage = document.querySelector('[data-cpu-page]');
  const memoryPage = document.querySelector('[data-memory-page]');
  const networkPage = document.querySelector('[data-network-page]');

  if (!cpuPage && !memoryPage && !networkPage) {
    return;
  }

  const liveScope = cpuPage ? 'cpu' : (memoryPage ? 'memory' : 'network');
  const liveControls = document.querySelector('[data-live-controls]');
  const livePause = document.querySelector('[data-live-pause]');
  const liveRefresh = document.querySelector('[data-live-refresh]');
  const liveIntervalKey = `tpanel.live.${liveScope}.interval`;
  const livePausedKey = `tpanel.live.${liveScope}.paused`;
  let livePaused = window.localStorage.getItem(livePausedKey) === '1';

  liveControls?.removeAttribute('hidden');

  if (liveRefresh) {
    const storedInterval = window.localStorage.getItem(liveIntervalKey);

    if (storedInterval && [...liveRefresh.options].some((option) => option.value === storedInterval)) {
      liveRefresh.value = storedInterval;
    } else {
      liveRefresh.value = '5000';
    }
  }

  const liveDelay = () => Number(liveRefresh?.value || 5000);

  const setLivePaused = (paused) => {
    livePaused = paused;
    window.localStorage.setItem(livePausedKey, paused ? '1' : '0');

    if (livePause) {
      livePause.textContent = paused ? '▶' : 'Ⅱ';
      livePause.classList.toggle('is-paused', paused);
      livePause.setAttribute('aria-label', paused ? 'Retomar atualização' : 'Pausar atualização');
    }
  };

  liveRefresh?.addEventListener('change', () => {
    window.localStorage.setItem(liveIntervalKey, liveRefresh.value);
  });
  setLivePaused(livePaused);

  const initialData = document.getElementById('cpu-initial-data');
  const refreshSelect = liveRefresh || document.querySelector('[data-cpu-refresh]');
  const totalCanvas = document.querySelector('[data-cpu-chart="usage"]');
  const loadCanvas = document.querySelector('[data-cpu-chart="load"]');
  const coresTarget = document.querySelector('[data-cpu-cores]');
  const processesTarget = document.querySelector('[data-cpu-processes]');
  const countdown = document.querySelector('[data-live-countdown]') || document.querySelector('[data-cpu-countdown]');
  const countdownText = countdown?.querySelector('span');
  const history = [];
  let timer = null;
  let progressFrame = null;
  let nextRefreshAt = 0;
  let refreshStartedAt = 0;
  let loading = false;

  const parseInitial = () => {
    try {
      return JSON.parse(initialData?.textContent || '{}');
    } catch {
      return null;
    }
  };

  const formatPercent = (value) => `${Number(value || 0).toFixed(1)}%`;
  const formatMhz = (value) => (value === null || value === undefined ? 'n/a' : `${Number(value).toFixed(0)} MHz`);
  const formatTemp = (value) => (value === null || value === undefined ? 'n/a' : `${Number(value).toFixed(1)} °C`);
  const formatBytesCompact = (bytes) => {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = Math.abs(Number(bytes || 0));
    let index = 0;

    while (value >= 1024 && index < units.length - 1) {
      value /= 1024;
      index += 1;
    }

    const signed = Number(bytes || 0) < 0 ? '-' : '';

    return `${signed}${value.toFixed(value >= 10 || index === 0 ? 0 : 1)} ${units[index]}`;
  };
  const formatAxisValue = (value, suffix) => {
    if (suffix === 'B/s') {
      return `${formatBytesCompact(value)}/s`;
    }

    return `${Number(value).toFixed(value >= 10 ? 0 : 1)}${suffix}`;
  };
  const setTextFor = (scope, field, value) => {
    document.querySelectorAll(`[data-${scope}-field="${field}"]`).forEach((node) => {
      node.textContent = value;
    });
  };

  const setText = (field, value) => setTextFor('cpu', field, value);

  const pushHistory = (snapshot) => {
    if (history.length === 0) {
      for (let index = 0; index < 24; index += 1) {
        history.push(snapshot);
      }

      return;
    }

    history.push(snapshot);

    if (history.length > 90) {
      history.shift();
    }
  };

  const fitCanvas = (canvas) => {
    if (!(canvas instanceof HTMLCanvasElement)) {
      return null;
    }

    const rect = canvas.getBoundingClientRect();
    const ratio = window.devicePixelRatio || 1;
    const width = Math.max(320, Math.floor(rect.width));
    const height = Math.max(220, Math.floor(rect.height));

    if (canvas.width !== width * ratio || canvas.height !== height * ratio) {
      canvas.width = width * ratio;
      canvas.height = height * ratio;
    }

    const context = canvas.getContext('2d');

    if (!context) {
      return null;
    }

    context.setTransform(ratio, 0, 0, ratio, 0, 0);

    return { context, width, height };
  };

  const drawPlotBackground = (context, width, height) => {
    context.clearRect(0, 0, width, height);
    context.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--panel-soft').trim() || '#10151f';
    context.fillRect(0, 0, width, height);
  };

  const scaleFor = (values, hardMin = 0, hardMax = null) => {
    const finiteValues = values.map(Number).filter(Number.isFinite);

    if (finiteValues.length === 0) {
      return { min: hardMin, max: hardMax || 100 };
    }

    const observedMin = Math.min(...finiteValues);
    const observedMax = Math.max(...finiteValues);
    const span = Math.max(1, observedMax - observedMin);
    const padding = Math.max(2, span * 0.35);
    const min = Math.max(hardMin, Math.floor((observedMin - padding) * 10) / 10);
    const maxCandidate = Math.ceil((observedMax + padding) * 10) / 10;
    const max = hardMax === null ? maxCandidate : Math.min(hardMax, Math.max(maxCandidate, min + 5));

    if (max - min < 5) {
      return {
        min: Math.max(hardMin, Math.floor((observedMax - 2.5) * 10) / 10),
        max: hardMax === null ? Math.ceil((observedMax + 2.5) * 10) / 10 : Math.min(hardMax, Math.ceil((observedMax + 2.5) * 10) / 10),
      };
    }

    return { min, max };
  };

  const drawAxes = (context, width, height, bounds, suffix, points) => {
    const plot = { left: 54, right: 18, top: 20, bottom: 34 };
    const plotWidth = width - plot.left - plot.right;
    const plotHeight = height - plot.top - plot.bottom;

    context.strokeStyle = 'rgba(148, 163, 184, 0.18)';
    context.fillStyle = 'rgba(203, 213, 225, 0.78)';
    context.lineWidth = 1;
    context.font = '11px Inter, system-ui, sans-serif';
    context.textBaseline = 'middle';

    for (let i = 0; i <= 4; i += 1) {
      const y = plot.top + (plotHeight / 4) * i;
      const value = bounds.max - ((bounds.max - bounds.min) / 4) * i;

      context.beginPath();
      context.moveTo(plot.left, y);
      context.lineTo(width - plot.right, y);
      context.stroke();
      context.textAlign = 'right';
      context.fillText(formatAxisValue(value, suffix), plot.left - 10, y);
    }

    context.strokeStyle = 'rgba(148, 163, 184, 0.34)';
    context.beginPath();
    context.moveTo(plot.left, plot.top);
    context.lineTo(plot.left, height - plot.bottom);
    context.lineTo(width - plot.right, height - plot.bottom);
    context.stroke();

    context.textAlign = 'left';
    context.textBaseline = 'alphabetic';
    context.fillText(points.length > 1 ? `-${Math.round((points.at(-1).time - points[0].time) / 1000)}s` : '-0s', plot.left, height - 10);
    context.textAlign = 'right';
    context.fillText('agora', width - plot.right, height - 10);

    return { ...plot, width: plotWidth, height: plotHeight };
  };

  const yFor = (value, bounds, plot) => {
    const normalized = (Number(value) - bounds.min) / Math.max(0.1, bounds.max - bounds.min);

    return plot.top + plot.height - Math.min(1, Math.max(0, normalized)) * plot.height;
  };

  const xFor = (index, count, plot) => plot.left + (count <= 1 ? plot.width : (plot.width / (count - 1)) * index);

  const drawLine = (canvas, points, color, suffix = '%', hardMax = null, options = {}) => {
    const fitted = fitCanvas(canvas);

    if (!fitted) {
      return;
    }

    const { context, width, height } = fitted;
    const averagePoints = options.averagePoints || [];
    const values = points.concat(averagePoints).map((point) => point.value);
    const bounds = scaleFor(values, 0, hardMax);

    drawPlotBackground(context, width, height);
    const plot = drawAxes(context, width, height, bounds, suffix, points);

    if (points.length === 0) {
      return;
    }

    const drawSeries = (series, stroke, lineWidth = 2.5, dashed = false) => {
      if (series.length === 0) {
        return;
      }

      context.save();
      context.strokeStyle = stroke;
      context.lineWidth = lineWidth;
      context.lineJoin = 'round';
      context.lineCap = 'round';
      context.setLineDash(dashed ? [7, 5] : []);
      context.beginPath();

      series.forEach((point, index) => {
        const x = xFor(index, series.length, plot);
        const y = yFor(point.value, bounds, plot);

        if (index === 0) {
          context.moveTo(x, y);
        } else {
          context.lineTo(x, y);
        }
      });

      context.stroke();
      context.restore();
    };

    if (options.peakLine) {
      const peak = Math.max(...points.map((point) => Number(point.value || 0)));
      const y = yFor(peak, bounds, plot);
      context.save();
      context.strokeStyle = options.peakColor || 'rgba(251, 191, 36, 0.68)';
      context.fillStyle = options.peakColor || 'rgba(251, 191, 36, 0.86)';
      context.lineWidth = 1;
      context.setLineDash([5, 5]);
      context.beginPath();
      context.moveTo(plot.left, y);
      context.lineTo(width - plot.right, y);
      context.stroke();
      context.setLineDash([]);
      context.font = '11px Inter, system-ui, sans-serif';
      context.textAlign = 'right';
      context.textBaseline = 'bottom';
      context.fillText(`pico ${formatAxisValue(peak, suffix)}`, width - plot.right, Math.max(plot.top + 12, y - 4));
      context.restore();
    }

    drawSeries(averagePoints, options.averageColor || 'rgba(255, 255, 255, 0.72)', 2, true);
    drawSeries(points, color, 2.5, false);

    const last = points.at(-1);

    if (last) {
      const x = xFor(points.length - 1, points.length, plot);
      const y = yFor(last.value, bounds, plot);
      context.fillStyle = color;
      context.beginPath();
      context.arc(x, y, 4, 0, Math.PI * 2);
      context.fill();
    }
  };

  const drawCharts = () => {
    const now = Date.now();
    const pointsFor = (selector) => history.map((item, index) => ({
      value: selector(item),
      time: Date.parse(item.collectedAt || '') || now - ((history.length - index - 1) * Number(refreshSelect?.value || 5000)),
    }));

    drawLine(
      totalCanvas,
      pointsFor((item) => item.total?.usagePercent || 0),
      '#38bdf8',
      '%',
      100,
    );
    drawLine(
      loadCanvas,
      pointsFor((item) => item.load?.normalizedOne || 0),
      '#a3e635',
      '%',
      null,
    );
  };

  const renderCores = (snapshot) => {
    if (!coresTarget) {
      return;
    }

    coresTarget.innerHTML = (snapshot.cores || []).map((core) => {
      const usage = Number(core.usagePercent || 0);
      const severity = usage >= 90 ? 'CRITICAL' : usage >= 75 ? 'WARNING' : 'OK';

      return `
        <div class="core-meter severity-left-${severity}">
          <div class="core-meter-top">
            <strong>${core.label}</strong>
            <span>${formatPercent(usage)}</span>
          </div>
          <div class="core-meter-bar"><span style="width: ${Math.min(100, usage)}%"></span></div>
          <small>sys ${formatPercent(core.systemPercent)} | iowait ${formatPercent(core.iowaitPercent)}</small>
        </div>
      `;
    }).join('');
  };

  const renderProcesses = (snapshot) => {
    if (!processesTarget) {
      return;
    }

    processesTarget.innerHTML = (snapshot.topProcesses || []).map((process) => `
      <tr>
        <td>${process.pid}</td>
        <td>${escapeHtml(process.user || 'n/a')}</td>
        <td>${escapeHtml(process.command)}</td>
        <td>${formatPercent(process.cpuPercent)}</td>
        <td>${formatPercent(process.memoryPercent)}</td>
      </tr>
    `).join('');
  };

  const renderSnapshot = (snapshot) => {
    if (!snapshot) {
      return;
    }

    pushHistory(snapshot);
    replacePageAlerts('cpu', 'CPU', '/cpu', snapshot.statusReasons || []);
    setText('status', snapshot.status || 'OK');
    setText('usage', formatPercent(snapshot.total?.usagePercent));
    setText('user', formatPercent(snapshot.total?.userPercent));
    setText('system', formatPercent(snapshot.total?.systemPercent));
    setText('load', Number(snapshot.load?.one || 0).toFixed(2));
    setText('normalizedLoad', formatPercent(snapshot.load?.normalizedOne));
    setText('frequency', formatMhz(snapshot.frequency?.averageMhz));
    setText('frequencyMin', formatMhz(snapshot.frequency?.minMhz));
    setText('frequencyMax', formatMhz(snapshot.frequency?.maxMhz));
    setText('temperature', formatTemp(snapshot.temperatureCelsius));
    setText('threads', String(snapshot.identity?.logicalThreads || 0));
    setText('collectedAt', new Date(snapshot.collectedAt).toLocaleTimeString());
    renderCores(snapshot);
    renderProcesses(snapshot);
    drawCharts();
  };

  const fetchSnapshot = async () => {
    if (loading) {
      return;
    }

    loading = true;

    try {
      const response = await fetch('/cpu/live', { headers: { Accept: 'application/json' } });

      if (response.ok) {
        renderSnapshot(await response.json());
      }
    } finally {
      loading = false;
    }
  };

  const updateCountdown = () => {
    if (!countdown) {
      return;
    }

    if (livePaused) {
      countdown.style.setProperty('--refresh-progress', '0deg');

      if (countdownText) {
        countdownText.textContent = 'off';
      }

      return;
    }

    const duration = Math.max(1, nextRefreshAt - refreshStartedAt);
    const remaining = Math.max(0, nextRefreshAt - Date.now());
    const progress = Math.min(1, Math.max(0, 1 - remaining / duration));
    countdown.style.setProperty('--refresh-progress', `${progress * 360}deg`);

    if (countdownText) {
      countdownText.textContent = `${Math.ceil(remaining / 1000)}s`;
    }

    progressFrame = window.requestAnimationFrame(updateCountdown);
  };

  const schedule = () => {
    window.clearTimeout(timer);
    if (livePaused) {
      return;
    }

    const delay = liveDelay();
    refreshStartedAt = Date.now();
    nextRefreshAt = refreshStartedAt + delay;
    timer = window.setTimeout(async () => {
      await fetchSnapshot();
      schedule();
    }, delay);
  };

  const initMemoryPage = () => {
    const initialMemory = document.getElementById('memory-initial-data');
    const memoryRefresh = liveRefresh || document.querySelector('[data-memory-refresh]');
    const memoryCountdown = document.querySelector('[data-live-countdown]') || document.querySelector('[data-memory-countdown]');
    const memoryCountdownText = memoryCountdown?.querySelector('span');
    const memoryStack = document.querySelector('[data-memory-stack]');
    const memoryProcesses = document.querySelector('[data-memory-processes]');
    const memoryStatusReasons = document.querySelector('[data-memory-status-reasons]');
    const memoryInventory = document.querySelector('[data-memory-inventory]');
    const memoryHistory = [];
    let memoryTimer = null;
    let memoryStartedAt = 0;
    let memoryNextAt = 0;
    let memoryLoading = false;

    const parseMemoryInitial = () => {
      try {
        return JSON.parse(initialMemory?.textContent || '{}');
      } catch {
        return null;
      }
    };

    const formatBytes = (bytes) => {
      const units = ['B', 'KB', 'MB', 'GB', 'TB'];
      let value = Number(bytes || 0);
      let index = 0;

      while (value >= 1024 && index < units.length - 1) {
        value /= 1024;
        index += 1;
      }

      return `${value.toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
    };

    const pushMemoryHistory = (snapshot) => {
      if (memoryHistory.length === 0) {
        for (let index = 0; index < 24; index += 1) {
          memoryHistory.push(snapshot);
        }

        return;
      }

      memoryHistory.push(snapshot);

      if (memoryHistory.length > 90) {
        memoryHistory.shift();
      }
    };

    const renderMemoryStack = (snapshot) => {
      if (!memoryStack) {
        return;
      }

      const total = Math.max(1, Number(snapshot.ram?.totalBytes || 0));
      const cache = Number(snapshot.ram?.cachedBytes || 0) + Number(snapshot.ram?.buffersBytes || 0);
      const available = Number(snapshot.ram?.availableBytes || 0);
      const used = Math.max(0, Number(snapshot.ram?.usedBytes || 0) - cache);
      const segments = [
        ['used', used, 'Usada'],
        ['cache', cache, 'Cache/Buffers'],
        ['free', available, 'Disponível'],
      ];

      memoryStack.innerHTML = segments.map(([key, value, label]) => {
        const width = Math.max(2, Math.min(100, (Number(value) / total) * 100));

        return `<span class="memory-stack-${key}" style="width: ${width}%" title="${label}: ${formatBytes(value)}"></span>`;
      }).join('');
    };

    const renderMemoryProcesses = (snapshot) => {
      if (!memoryProcesses) {
        return;
      }

      memoryProcesses.innerHTML = (snapshot.topProcesses || []).map((process) => `
        <tr>
          <td>${process.pid}</td>
          <td>${escapeHtml(process.user || 'n/a')}</td>
          <td>${escapeHtml(process.command)}</td>
          <td>${formatPercent(process.memoryPercent)}</td>
          <td>${formatPercent(process.cpuPercent)}</td>
        </tr>
      `).join('');
    };

    const renderMemoryStatusReasons = (snapshot) => {
      if (!memoryStatusReasons) {
        return;
      }

      const reasons = snapshot.statusReasons || [];

      if (reasons.length === 0) {
        memoryStatusReasons.innerHTML = '<div class="memory-status-ok"><strong>OK</strong><span>Nenhum threshold operacional ativo.</span></div>';
        return;
      }

      memoryStatusReasons.innerHTML = reasons.map((reason) => `
        <div class="memory-status-reason severity-left-${escapeHtml(reason.severity || 'WARNING')}">
          <strong>${escapeHtml(reason.severity || 'WARNING')}</strong>
          <span>${escapeHtml(reason.label || 'Threshold ativo')}</span>
          <b>${escapeHtml(reason.value || '')}</b>
        </div>
      `).join('');
    };

    const renderMemoryInventory = (snapshot) => {
      const inventory = snapshot.inventory || {};
      const devices = inventory.devices || [];
      setTextFor('memory', 'inventorySummary', inventory.available ? 'SMBIOS detectado' : 'SMBIOS indisponível');
      setTextFor('memory', 'maximumCapacity', inventory.maximumCapacity || 'n/a');
      setTextFor(
        'memory',
        'slotSummary',
        inventory.deviceCount === null || inventory.deviceCount === undefined
          ? `${devices.length} ocupado(s)`
          : `${devices.length}/${inventory.deviceCount} ocupado(s)`,
      );
      setTextFor('memory', 'eccType', inventory.errorCorrectionType || 'n/a');

      if (!memoryInventory) {
        return;
      }

      if (devices.length === 0) {
        memoryInventory.innerHTML = '<tr><td colspan="8">Inventário físico indisponível para o usuário do Apache.</td></tr>';
        return;
      }

      memoryInventory.innerHTML = devices.map((device) => `
        <tr>
          <td>${escapeHtml(device.locator || 'n/a')}</td>
          <td>${escapeHtml(device.size || 'n/a')}</td>
          <td>${escapeHtml(device.type || 'n/a')} / ${escapeHtml(device.formFactor || 'n/a')}</td>
          <td>${escapeHtml(device.speed || 'n/a')}</td>
          <td>${escapeHtml(device.configuredSpeed || 'n/a')}</td>
          <td>${escapeHtml(device.rank || 'n/a')}</td>
          <td>${escapeHtml(device.partNumber || 'n/a')}</td>
          <td>${escapeHtml(device.serialNumber || 'n/a')}</td>
        </tr>
      `).join('');
    };

    const drawMemoryCharts = () => {
      const now = Date.now();
      const pointsFor = (selector) => memoryHistory.map((item, index) => ({
        value: selector(item),
        time: Date.parse(item.collectedAt || '') || now - ((memoryHistory.length - index - 1) * Number(memoryRefresh?.value || 5000)),
      }));

      drawLine(document.querySelector('[data-memory-chart="ram"]'), pointsFor((item) => item.ram?.usedPercent || 0), '#38bdf8', '%', 100);
      drawLine(document.querySelector('[data-memory-chart="swap"]'), pointsFor((item) => item.swap?.usedPercent || 0), '#f59e0b', '%', 100);
      drawLine(document.querySelector('[data-memory-chart="pressure"]'), pointsFor((item) => item.pressure?.some?.avg10 || 0), '#a3e635', '%', null);
      drawLine(document.querySelector('[data-memory-chart="paging"]'), pointsFor((item) => (item.paging?.pageOutKbPerSecond || 0) / 1024), '#fb7185', 'MB/s', null);
    };

    const renderMemorySnapshot = (snapshot) => {
      if (!snapshot) {
        return;
      }

      const cacheBuffers = Number(snapshot.ram?.cachedBytes || 0) + Number(snapshot.ram?.buffersBytes || 0);
      pushMemoryHistory(snapshot);
      replacePageAlerts('memory', 'Memória', '/memory', snapshot.statusReasons || []);
      setTextFor('memory', 'status', snapshot.status || 'OK');
      document.querySelectorAll('[data-memory-field="status"]').forEach((node) => {
        node.classList.remove('severity-OK', 'severity-WARNING', 'severity-CRITICAL', 'severity-UNAVAILABLE');
        node.classList.add(`severity-${snapshot.status || 'OK'}`);
      });
      setTextFor('memory', 'ramUsage', formatPercent(snapshot.ram?.usedPercent));
      setTextFor('memory', 'ramUsed', formatBytes(snapshot.ram?.usedBytes));
      setTextFor('memory', 'ramAvailable', formatBytes(snapshot.ram?.availableBytes));
      setTextFor('memory', 'ramTotal', formatBytes(snapshot.ram?.totalBytes));
      setTextFor('memory', 'cacheBuffers', formatBytes(cacheBuffers));
      setTextFor('memory', 'reclaimable', formatBytes(snapshot.kernel?.sReclaimableBytes));
      setTextFor('memory', 'swapUsage', formatPercent(snapshot.swap?.usedPercent));
      setTextFor('memory', 'swapUsed', formatBytes(snapshot.swap?.usedBytes));
      setTextFor('memory', 'swapActivity', `${Number(snapshot.swap?.activityPagesPerSecond || 0).toFixed(2)} p/s`);
      setTextFor('memory', 'active', formatBytes(snapshot.ram?.activeBytes));
      setTextFor('memory', 'inactive', formatBytes(snapshot.ram?.inactiveBytes));
      setTextFor('memory', 'anon', formatBytes(snapshot.ram?.anonBytes));
      setTextFor('memory', 'mapped', formatBytes(snapshot.ram?.mappedBytes));
      setTextFor('memory', 'shmem', formatBytes(snapshot.ram?.shmemBytes));
      setTextFor('memory', 'slab', formatBytes(snapshot.kernel?.slabBytes));
      setTextFor('memory', 'dirty', formatBytes(snapshot.kernel?.dirtyBytes));
      setTextFor('memory', 'writeback', formatBytes(snapshot.kernel?.writebackBytes));
      setTextFor('memory', 'collectedAt', new Date(snapshot.collectedAt).toLocaleTimeString());
      renderMemoryStack(snapshot);
      renderMemoryProcesses(snapshot);
      renderMemoryStatusReasons(snapshot);
      renderMemoryInventory(snapshot);
      drawMemoryCharts();
    };

    const fetchMemorySnapshot = async () => {
      if (memoryLoading) {
        return;
      }

      memoryLoading = true;

      try {
        const response = await fetch('/memory/live', { headers: { Accept: 'application/json' } });

        if (response.ok) {
          renderMemorySnapshot(await response.json());
        }
      } finally {
        memoryLoading = false;
      }
    };

    const updateMemoryCountdown = () => {
      if (!memoryCountdown) {
        return;
      }

      if (livePaused) {
        memoryCountdown.style.setProperty('--refresh-progress', '0deg');

        if (memoryCountdownText) {
          memoryCountdownText.textContent = 'off';
        }

        return;
      }

      const duration = Math.max(1, memoryNextAt - memoryStartedAt);
      const remaining = Math.max(0, memoryNextAt - Date.now());
      const progress = Math.min(1, Math.max(0, 1 - remaining / duration));
      memoryCountdown.style.setProperty('--refresh-progress', `${progress * 360}deg`);

      if (memoryCountdownText) {
        memoryCountdownText.textContent = `${Math.ceil(remaining / 1000)}s`;
      }

      window.requestAnimationFrame(updateMemoryCountdown);
    };

    const scheduleMemory = () => {
      window.clearTimeout(memoryTimer);
      if (livePaused) {
        return;
      }

      const delay = liveDelay();
      memoryStartedAt = Date.now();
      memoryNextAt = memoryStartedAt + delay;
      memoryTimer = window.setTimeout(async () => {
        await fetchMemorySnapshot();
        scheduleMemory();
      }, delay);
    };

    livePause?.addEventListener('click', () => {
      setLivePaused(!livePaused);
      scheduleMemory();
      updateMemoryCountdown();
    });
    memoryRefresh?.addEventListener('change', scheduleMemory);
    window.addEventListener('resize', drawMemoryCharts);
    renderMemorySnapshot(parseMemoryInitial());
    scheduleMemory();
    updateMemoryCountdown();
  };

  const initNetworkPage = () => {
    const initialNetwork = document.getElementById('network-initial-data');
    const networkRefresh = liveRefresh;
    const networkCountdown = document.querySelector('[data-live-countdown]');
    const networkCountdownText = networkCountdown?.querySelector('span');
    const networkStatusReasons = document.querySelector('[data-network-status-reasons]');
    const networkInterfaces = document.querySelector('[data-network-interfaces]');
    const networkListeners = document.querySelector('[data-network-listeners]');
    const networkApplications = document.querySelector('[data-network-applications]');
    const networkConnections = document.querySelector('[data-network-connections]');
    const networkRoutes = document.querySelector('[data-network-routes]');
    const networkDns = document.querySelector('[data-network-dns]');
    const networkHistory = [];
    let networkTimer = null;
    let networkStartedAt = 0;
    let networkNextAt = 0;
    let networkLoading = false;

    const parseNetworkInitial = () => {
      try {
        return JSON.parse(initialNetwork?.textContent || '{}');
      } catch {
        return null;
      }
    };

    const formatBytes = (bytes) => {
      const units = ['B', 'KB', 'MB', 'GB', 'TB'];
      let value = Number(bytes || 0);
      let index = 0;

      while (value >= 1024 && index < units.length - 1) {
        value /= 1024;
        index += 1;
      }

      return `${value.toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
    };

    const formatRate = (bytes) => `${formatBytes(bytes)}/s`;
    const endpoint = (address, port) => `${address || 'n/a'}:${port || '*'}`;
    const networkWindowMs = 10 * 60 * 1000;

    const pushNetworkHistory = (snapshot) => {
      const interval = Number(networkRefresh?.value || 5000);

      if (networkHistory.length === 0) {
        const firstTime = Date.parse(snapshot.collectedAt || '') || Date.now();
        const seedCount = Math.min(24, Math.max(2, Math.ceil(networkWindowMs / Math.max(interval, 1))));

        for (let index = seedCount - 1; index >= 0; index -= 1) {
          networkHistory.push({
            ...snapshot,
            collectedAt: new Date(firstTime - (index * interval)).toISOString(),
          });
        }

        return;
      }

      networkHistory.push(snapshot);

      const latestTime = Date.parse(snapshot.collectedAt || '') || Date.now();
      const cutoff = latestTime - networkWindowMs;

      while (networkHistory.length > 2 && (Date.parse(networkHistory[0].collectedAt || '') || latestTime) < cutoff) {
        networkHistory.shift();
      }
    };

    const renderNetworkStatusReasons = (snapshot) => {
      if (!networkStatusReasons) {
        return;
      }

      const reasons = snapshot.statusReasons || [];

      if (reasons.length === 0) {
        networkStatusReasons.innerHTML = '<div class="memory-status-ok"><strong>OK</strong><span>Nenhum threshold operacional ativo.</span></div>';
        return;
      }

      networkStatusReasons.innerHTML = reasons.map((reason) => `
        <div class="memory-status-reason severity-left-${escapeHtml(reason.severity || 'WARNING')}">
          <strong>${escapeHtml(reason.severity || 'WARNING')}</strong>
          <span>${escapeHtml(reason.label || 'Alerta de rede')}</span>
          <b>${escapeHtml(reason.value || '')}</b>
        </div>
      `).join('');
    };

    const renderNetworkInterfaces = (snapshot) => {
      if (!networkInterfaces) {
        return;
      }

      const interfaces = snapshot.interfaces || [];

      if (interfaces.length === 0) {
        networkInterfaces.innerHTML = '<div class="network-interface-card severity-left-CRITICAL"><strong>Nenhuma interface detectada</strong><span>Sem dados em /proc/net/dev.</span></div>';
        return;
      }

      networkInterfaces.innerHTML = interfaces.map((iface) => {
        const severity = iface.state === 'UP' ? 'OK' : 'WARNING';
        const addresses = (iface.addresses || []).length > 0 ? iface.addresses.join(', ') : 'sem IP';

        return `
          <div class="network-interface-card severity-left-${severity}">
            <div><strong>${escapeHtml(iface.name)}</strong><span class="severity-badge severity-${severity}">${escapeHtml(iface.state || 'UNKNOWN')}</span></div>
            <span>${escapeHtml(addresses)}</span>
            <small>MAC ${escapeHtml(iface.mac || 'n/a')} | MTU ${escapeHtml(iface.mtu || 'n/a')}</small>
            <div class="network-rate-row"><b>↓ ${formatRate(iface.rxBytesPerSecond)}</b><b>↑ ${formatRate(iface.txBytesPerSecond)}</b></div>
            <small>erros ${Number(iface.rxErrors || 0) + Number(iface.txErrors || 0)} | descartes ${Number(iface.rxDropped || 0) + Number(iface.txDropped || 0)}</small>
          </div>
        `;
      }).join('');
    };

    const renderNetworkListeners = (snapshot) => {
      if (!networkListeners) {
        return;
      }

      const rows = snapshot.listeners || [];

      if (rows.length === 0) {
        networkListeners.innerHTML = '<tr><td colspan="6">Nenhum listener retornado pelo ss.</td></tr>';
        return;
      }

      networkListeners.innerHTML = rows.map((row) => `
        <tr>
          <td>${escapeHtml(row.protocol || 'n/a')}</td>
          <td>${escapeHtml(row.localPort || row.port || '*')}</td>
          <td>${escapeHtml(row.localAddress || 'n/a')}</td>
          <td><span class="network-scope network-scope-${escapeHtml(row.scope || 'BOUND')}">${escapeHtml(row.scope || 'BOUND')}</span></td>
          <td>${escapeHtml(row.process || 'n/a')}${row.pid ? ` <small>pid ${escapeHtml(row.pid)}</small>` : ''}</td>
          <td>${escapeHtml(row.firewallStatus || 'n/a')}</td>
        </tr>
      `).join('');
    };

    const renderNetworkApplications = (snapshot) => {
      if (!networkApplications) {
        return;
      }

      const rows = snapshot.topApplications || [];

      if (rows.length === 0) {
        networkApplications.innerHTML = '<tr><td colspan="6">Nenhuma aplicação correlacionada aos sockets.</td></tr>';
        return;
      }

      networkApplications.innerHTML = rows.map((row) => `
        <tr>
          <td>${escapeHtml(row.pid || 'n/a')}</td>
          <td>${escapeHtml(row.process || 'desconhecido')}</td>
          <td>${escapeHtml(row.listeners || 0)}</td>
          <td>${escapeHtml(row.connections || 0)}</td>
          <td>${formatBytes(row.queuedBytes || 0)}</td>
          <td>${escapeHtml((row.ports || []).join(', ') || 'n/a')}</td>
        </tr>
      `).join('');
    };

    const renderNetworkConnections = (snapshot) => {
      if (!networkConnections) {
        return;
      }

      const rows = snapshot.connections || [];

      if (rows.length === 0) {
        networkConnections.innerHTML = '<tr><td colspan="6">Nenhuma conexão estabelecida retornada pelo ss.</td></tr>';
        return;
      }

      networkConnections.innerHTML = rows.slice(0, 18).map((row) => `
        <tr>
          <td>${escapeHtml(row.protocol || 'n/a')}</td>
          <td>${escapeHtml(endpoint(row.localAddress, row.localPort))}</td>
          <td>${escapeHtml(endpoint(row.peerAddress, row.peerPort))}</td>
          <td>${formatBytes(row.receiveQueue || 0)}</td>
          <td>${formatBytes(row.sendQueue || 0)}</td>
          <td>${escapeHtml(row.process || 'n/a')}${row.pid ? ` <small>pid ${escapeHtml(row.pid)}</small>` : ''}</td>
        </tr>
      `).join('');
    };

    const renderNetworkRoutes = (snapshot) => {
      if (!networkRoutes) {
        return;
      }

      const rows = snapshot.routes || [];

      if (rows.length === 0) {
        networkRoutes.innerHTML = '<tr><td colspan="4">Nenhuma rota retornada pelo ip route.</td></tr>';
        return;
      }

      networkRoutes.innerHTML = rows.map((row) => `
        <tr>
          <td>${escapeHtml(row.destination || 'default')}</td>
          <td>${escapeHtml(row.gateway || 'n/a')}</td>
          <td>${escapeHtml(row.device || 'n/a')}</td>
          <td>${escapeHtml(row.source || 'n/a')}</td>
        </tr>
      `).join('');
    };

    const renderNetworkDns = (snapshot) => {
      if (!networkDns) {
        return;
      }

      const servers = snapshot.dnsServers || [];
      networkDns.innerHTML = `
        <strong>DNS</strong>
        <div>${servers.length === 0 ? '<span>n/a</span>' : servers.map((server) => `<span>${escapeHtml(server)}</span>`).join('')}</div>
      `;
    };

    const movingAverage = (points, windowMs) => points.map((point, index) => {
      const start = Number(point.time || 0) - windowMs;
      const values = points
        .slice(0, index + 1)
        .filter((candidate) => Number(candidate.time || 0) >= start)
        .map((candidate) => Number(candidate.value || 0));
      const average = values.length === 0 ? Number(point.value || 0) : values.reduce((sum, value) => sum + value, 0) / values.length;

      return {
        ...point,
        value: average,
      };
    });

    const drawNetworkCharts = () => {
      const now = Date.now();
      const pointsFor = (selector) => networkHistory.map((item, index) => ({
        value: selector(item),
        time: Date.parse(item.collectedAt || '') || now - ((networkHistory.length - index - 1) * Number(networkRefresh?.value || 5000)),
      }));

      const rxPoints = pointsFor((item) => item.summary?.rxBytesPerSecond || 0);
      const txPoints = pointsFor((item) => item.summary?.txBytesPerSecond || 0);

      drawLine(document.querySelector('[data-network-chart="rx"]'), rxPoints, '#38bdf8', 'B/s', null, {
        averagePoints: movingAverage(rxPoints, networkWindowMs),
        averageColor: 'rgba(255, 255, 255, 0.72)',
        peakLine: true,
        peakColor: 'rgba(251, 191, 36, 0.82)',
      });
      drawLine(document.querySelector('[data-network-chart="tx"]'), txPoints, '#a3e635', 'B/s', null, {
        averagePoints: movingAverage(txPoints, networkWindowMs),
        averageColor: 'rgba(255, 255, 255, 0.72)',
        peakLine: true,
        peakColor: 'rgba(251, 191, 36, 0.82)',
      });
    };

    const renderNetworkSnapshot = (snapshot) => {
      if (!snapshot) {
        return;
      }

      pushNetworkHistory(snapshot);
      replacePageAlerts('network', 'Rede', '/network', snapshot.statusReasons || []);
      setTextFor('network', 'rxRate', formatRate(snapshot.summary?.rxBytesPerSecond));
      setTextFor('network', 'txRate', formatRate(snapshot.summary?.txBytesPerSecond));
      setTextFor('network', 'activeInterfaces', String(snapshot.summary?.activeInterfaceCount || 0));
      setTextFor('network', 'interfaceCount', String(snapshot.summary?.interfaceCount || 0));
      setTextFor('network', 'listenerCount', String(snapshot.summary?.listenerCount || 0));
      setTextFor('network', 'publicListenerCount', String(snapshot.summary?.publicListenerCount || 0));
      setTextFor('network', 'connectionCount', String(snapshot.summary?.connectionCount || 0));
      setTextFor('network', 'firewallState', snapshot.summary?.firewallAvailable ? 'detectado' : 'não verificado');
      setTextFor('network', 'collectedAt', new Date(snapshot.collectedAt).toLocaleTimeString());
      renderNetworkStatusReasons(snapshot);
      renderNetworkInterfaces(snapshot);
      renderNetworkListeners(snapshot);
      renderNetworkApplications(snapshot);
      renderNetworkConnections(snapshot);
      renderNetworkRoutes(snapshot);
      renderNetworkDns(snapshot);
      drawNetworkCharts();
    };

    const fetchNetworkSnapshot = async () => {
      if (networkLoading) {
        return;
      }

      networkLoading = true;

      try {
        const response = await fetch('/network/live', { headers: { Accept: 'application/json' } });

        if (response.ok) {
          renderNetworkSnapshot(await response.json());
        }
      } finally {
        networkLoading = false;
      }
    };

    const updateNetworkCountdown = () => {
      if (!networkCountdown) {
        return;
      }

      if (livePaused) {
        networkCountdown.style.setProperty('--refresh-progress', '0deg');

        if (networkCountdownText) {
          networkCountdownText.textContent = 'off';
        }

        return;
      }

      const duration = Math.max(1, networkNextAt - networkStartedAt);
      const remaining = Math.max(0, networkNextAt - Date.now());
      const progress = Math.min(1, Math.max(0, 1 - remaining / duration));
      networkCountdown.style.setProperty('--refresh-progress', `${progress * 360}deg`);

      if (networkCountdownText) {
        networkCountdownText.textContent = `${Math.ceil(remaining / 1000)}s`;
      }

      window.requestAnimationFrame(updateNetworkCountdown);
    };

    const scheduleNetwork = () => {
      window.clearTimeout(networkTimer);
      if (livePaused) {
        return;
      }

      const delay = liveDelay();
      networkStartedAt = Date.now();
      networkNextAt = networkStartedAt + delay;
      networkTimer = window.setTimeout(async () => {
        await fetchNetworkSnapshot();
        scheduleNetwork();
      }, delay);
    };

    livePause?.addEventListener('click', () => {
      setLivePaused(!livePaused);
      scheduleNetwork();
      updateNetworkCountdown();
    });
    networkRefresh?.addEventListener('change', scheduleNetwork);
    window.addEventListener('resize', drawNetworkCharts);
    renderNetworkSnapshot(parseNetworkInitial());
    scheduleNetwork();
    updateNetworkCountdown();
  };

  if (cpuPage) {
    livePause?.addEventListener('click', () => {
      setLivePaused(!livePaused);
      schedule();
      updateCountdown();
    });
    refreshSelect?.addEventListener('change', schedule);
    window.addEventListener('resize', drawCharts);
    renderSnapshot(parseInitial());
    schedule();
    updateCountdown();
  }

  if (memoryPage) {
    initMemoryPage();
  }

  if (networkPage) {
    initNetworkPage();
  }
})();
