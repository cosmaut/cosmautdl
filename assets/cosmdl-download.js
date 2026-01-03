/*
 * CosmautDL 下载页交互脚本
 * 功能：网盘提取码复制、扫码解锁弹窗打开、轮询解锁状态并恢复下载按钮。
 * 说明：通过 wp_enqueue_script 输出，尽量避免主题过滤导致脚本丢失。
 */

(function () {
  'use strict';

  // 中文注释：从 wp_localize_script 注入配置（若不存在则使用空对象兜底）
  var cfg = window.cosmdlDownload || {};
  var ajaxUrl = cfg.ajaxUrl || '';
  var ajaxNonce = cfg.nonce || '';
  var pollInterval = parseInt(cfg.pollInterval, 10);
  if (!pollInterval || pollInterval < 1000) {
    pollInterval = 3000;
  }

  // 中文注释：行为开关（默认开启：解锁后自动关闭弹窗并自动跳转）
  var autoCloseOnUnlock = cfg.autoCloseOnUnlock !== 0;
  var autoRedirectOnUnlock = cfg.autoRedirectOnUnlock !== 0;

  var i18n = cfg.i18n || {};

  function getText(key, fallback) {
    if (i18n && Object.prototype.hasOwnProperty.call(i18n, key) && i18n[key]) {
      return i18n[key];
    }
    return fallback;
  }

  // 中文注释：文本复制（优先 Clipboard API，失败则回退 execCommand）
  function copyToClipboard(text) {
    if (!text) {
      return Promise.resolve(false);
    }

    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      return navigator.clipboard
        .writeText(text)
        .then(function () {
          return true;
        })
        .catch(function () {
          return fallbackCopy(text);
        });
    }

    return fallbackCopy(text);
  }

  function fallbackCopy(text) {
    try {
      var input = document.createElement('input');
      input.setAttribute('value', text);
      input.setAttribute('readonly', 'readonly');
      input.style.position = 'fixed';
      input.style.left = '-9999px';
      input.style.top = '0';
      document.body.appendChild(input);
      input.select();
      input.setSelectionRange(0, input.value.length);
      var ok = document.execCommand('copy');
      document.body.removeChild(input);
      return Promise.resolve(!!ok);
    } catch (e) {
      return Promise.resolve(false);
    }
  }

  // 中文注释：将元素的文本短暂替换为提示文案
  function flashInnerText(el, text, ms) {
    if (!el) {
      return;
    }
    var original = el.innerText;
    el.innerText = text;
    window.setTimeout(function () {
      el.innerText = original;
    }, ms || 2000);
  }

  function getModal() {
    return document.querySelector('.cosmdl-qr-modal');
  }

  function openModal() {
    var modal = getModal();
    if (!modal) {
      return;
    }
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeModal() {
    var modal = getModal();
    if (!modal) {
      return;
    }
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
  }

  // 中文注释：轮询状态（同一页只启动一个轮询，避免重复请求）
  var pollTimer = null;
  var pollingScene = '';

  // 中文注释：记录用户最后一次点击的“锁定按钮”，用于解锁后自动跳转
  var lastLockedClick = {
    scene: '',
    url: '',
    target: '',
  };

  var redirectedScene = '';

  function redirectToUrl(url, target) {
    if (!url) {
      return;
    }

    // 中文注释：优先保持“解锁后按钮的既定行为”（通常是新开标签页）
    if (target === '_blank') {
      var w = null;
      try {
        w = window.open(url, '_blank', 'noopener');
      } catch (e) {
        w = null;
      }

      // 中文注释：若浏览器拦截了自动弹窗，则回退为当前页跳转，避免用户卡住
      if (!w) {
        window.location.href = url;
      }
      return;
    }

    window.location.href = url;
  }

  function startPolling(scene) {
    if (!ajaxUrl || !scene) {
      return;
    }
    if (pollTimer && pollingScene === scene) {
      return;
    }

    stopPolling();
    pollingScene = scene;

    pollTimer = window.setInterval(function () {
      try {
        var xhr = new XMLHttpRequest();
        var req = ajaxUrl + '?action=cosmdl_check_unlock&scene=' + encodeURIComponent(scene);
        if (ajaxNonce) {
          req += '&_ajax_nonce=' + encodeURIComponent(ajaxNonce);
        }
        xhr.open('GET', req, true);
        xhr.onreadystatechange = function () {
          if (xhr.readyState !== 4) {
            return;
          }
          if (xhr.status !== 200) {
            return;
          }

          var data = null;
          try {
            data = JSON.parse(xhr.responseText);
          } catch (e) {
            return;
          }

          if (data && data.unlocked) {
            stopPolling();
            applyUnlocked(scene);
          }
        };
        xhr.send(null);
      } catch (e) {
        // 中文注释：请求异常时忽略，等待下一次轮询
      }
    }, pollInterval);
  }

  function stopPolling() {
    if (pollTimer) {
      window.clearInterval(pollTimer);
      pollTimer = null;
    }
    pollingScene = '';
  }

  function applyUnlocked(scene) {
    // 中文注释：更新弹窗状态文案
    var statusEl = document.querySelector('.cosmdl-qr-status');
    if (statusEl) {
      statusEl.setAttribute('data-status', 'done');
      statusEl.innerText = getText('unlockedStatus', '已解锁，请点击下方网盘按钮开始下载');
    }

    // 中文注释：恢复全部锁定按钮为可用状态
    var lockedBtns = document.querySelectorAll('a.cosmdl-pan-btn-locked');
    for (var i = 0; i < lockedBtns.length; i++) {
      var btn = lockedBtns[i];
      var targetUrl = btn.getAttribute('data-target-url');
      var target = btn.getAttribute('data-target');
      var rel = btn.getAttribute('data-rel');
      var btnScene = btn.getAttribute('data-scene') || '';

      // 中文注释：仅解锁同一个 scene 的按钮，避免同页多附件时误解锁
      if (btnScene && btnScene !== scene) {
        continue;
      }

      if (targetUrl) {
        btn.setAttribute('href', targetUrl);
      }
      if (target) {
        btn.setAttribute('target', target);
      }
      if (rel) {
        btn.setAttribute('rel', rel);
      }
      btn.classList.remove('cosmdl-pan-btn-locked');
    }

    if (autoCloseOnUnlock) {
      closeModal();
    }

    if (
      autoRedirectOnUnlock &&
      scene &&
      redirectedScene !== scene &&
      lastLockedClick &&
      lastLockedClick.scene === scene &&
      lastLockedClick.url
    ) {
      redirectedScene = scene;
      window.setTimeout(function () {
        redirectToUrl(lastLockedClick.url, lastLockedClick.target);
      }, 200);
    }
  }

  function handleLockedButtonClick(e, lockedBtn) {
    if (!lockedBtn) {
      return;
    }

    if (e && typeof e.preventDefault === 'function') {
      e.preventDefault();
    }
    if (e && typeof e.stopPropagation === 'function') {
      e.stopPropagation();
    }
    if (e && typeof e.stopImmediatePropagation === 'function') {
      e.stopImmediatePropagation();
    }

    var statusEl = document.querySelector('.cosmdl-qr-status');
    if (statusEl) {
      statusEl.setAttribute('data-status', 'waiting');
      statusEl.innerText = getText('needUnlock', '请先使用微信扫码二维码，完成验证后再点击下载按钮');
    }

    openModal();

    var scene = lockedBtn.getAttribute('data-scene') || '';
    lastLockedClick.scene = scene;
    lastLockedClick.url = lockedBtn.getAttribute('data-target-url') || '';
    lastLockedClick.target = lockedBtn.getAttribute('data-target') || '_blank';

    startPolling(scene);
  }

  function stopEvent(e, preventDefault) {
    if (!e) {
      return;
    }
    if (preventDefault && typeof e.preventDefault === 'function') {
      e.preventDefault();
    }
    if (typeof e.stopPropagation === 'function') {
      e.stopPropagation();
    }
    if (typeof e.stopImmediatePropagation === 'function') {
      e.stopImmediatePropagation();
    }
  }

  document.addEventListener(
    'click',
    function (e) {
      var lockedBtn = e.target && e.target.closest ? e.target.closest('a.cosmdl-pan-btn.cosmdl-pan-btn-locked') : null;
      if (lockedBtn) {
        handleLockedButtonClick(e, lockedBtn);
        return;
      }

      var modalDialog = e.target && e.target.closest ? e.target.closest('.cosmdl-qr-modal-dialog') : null;
      if (modalDialog) {
        var closeBtn = e.target && e.target.closest ? e.target.closest('.cosmdl-qr-modal-close') : null;
        if (!closeBtn) {
          var qrImgInsideDialog = e.target && e.target.closest ? e.target.closest('.cosmdl-qr-modal img.cosmdl-qr-image') : null;
          stopEvent(e, !!qrImgInsideDialog);
          return;
        }
      }

      var qrImg = e.target && e.target.closest ? e.target.closest('.cosmdl-qr-modal img.cosmdl-qr-image') : null;
      if (qrImg) {
        stopEvent(e, true);
      }
    },
    true
  );

  // 中文注释：事件统一委托，确保动态渲染内容也能正常响应
  document.addEventListener('click', function (e) {
    // 1) 提取码复制
    var pwdEl = e.target && e.target.closest ? e.target.closest('.cosmdl-pan-pwd[data-cosmdl-copy]') : null;
    if (pwdEl) {
      e.preventDefault();
      e.stopPropagation();

      var copyText = pwdEl.getAttribute('data-cosmdl-copy') || '';
      copyToClipboard(copyText).then(function (ok) {
        if (ok) {
          flashInnerText(pwdEl, getText('copied', '已复制'), 2000);
        } else {
          flashInnerText(pwdEl, getText('copyFailed', '复制失败，请手动复制'), 2000);
        }
      });

      return;
    }

    // 2) 弹窗关闭
    var closeBtn = e.target && e.target.closest ? e.target.closest('.cosmdl-qr-modal-close') : null;
    if (closeBtn) {
      e.preventDefault();
      closeModal();
      return;
    }

    // 点击遮罩关闭：支持点击 .cosmdl-qr-modal 或 backdrop
    if (e.target && (e.target.classList.contains('cosmdl-qr-modal') || e.target.classList.contains('cosmdl-qr-modal-backdrop'))) {
      closeModal();
      return;
    }

    // 3) 锁定按钮：打开弹窗并开始轮询
    var lockedBtn = e.target && e.target.closest ? e.target.closest('a.cosmdl-pan-btn.cosmdl-pan-btn-locked') : null;
    if (lockedBtn) {
      handleLockedButtonClick(e, lockedBtn);
      return;
    }
  });

  // 中文注释：ESC 关闭弹窗
  document.addEventListener('keydown', function (e) {
    if (e && e.key === 'Escape') {
      closeModal();
    }
  });

  // 中文注释：为兼容历史内联调用，保留全局函数（不依赖正文内 <script>）
  window.cosmdl_copy_text = function (text, el) {
    copyToClipboard(text).then(function (ok) {
      if (ok) {
        flashInnerText(el, getText('copied', '已复制'), 2000);
      } else {
        flashInnerText(el, getText('copyFailed', '复制失败，请手动复制'), 2000);
      }
    });
  };
})();
