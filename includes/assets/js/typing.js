/* Moelog AI Q&A - typing.js (no inline needed) */
(function () {
  'use strict';

  // ---- singleton guard（避免被載入兩次時重跑）----
  if (window.__MOELOG_AIQNA_INIT__) return;
  window.__MOELOG_AIQNA_INIT__ = true;

  // ---- 全域設定（若外部未覆寫，走預設值）----
  // 可選：若想覆寫，請在另一支「外部檔」先行設置 window.MoelogAIQnA = { typing_ms: 10, ... }
  var G = window.MoelogAIQnA || {};
  var CFG = {
    typing_ms:
      typeof G.typing_ms === 'number' && G.typing_ms >= 0 ? G.typing_ms : 12,
    typing_jitter_ms:
      typeof G.typing_jitter_ms === 'number' && G.typing_jitter_ms >= 0
        ? G.typing_jitter_ms
        : 6,
    typing_disabled: !!G.typing_disabled,
    typing_max_chars:
      typeof G.typing_max_chars === 'number' && G.typing_max_chars >= 0
        ? G.typing_max_chars
        : 1200,
    typing_fallback:
      typeof G.typing_fallback === 'string' && G.typing_fallback.trim().length
        ? G.typing_fallback
        : '抱歉,目前無法取得 AI 回答,請稍後再試。',
  };

  // ---- 工具：安全複製節點（僅允許白名單標籤 & 必要屬性）----
  var ALLOWED = new Set(['P', 'UL', 'OL', 'LI', 'STRONG', 'EM', 'BR', 'SPAN', 'A', 'DIV', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD', 'CODE', 'PRE', 'BLOCKQUOTE', 'HR', 'DEL']);

  function cloneShallow(node) {
    if (node.nodeType === Node.TEXT_NODE) return document.createTextNode('');
    if (node.nodeType === Node.ELEMENT_NODE) {
      var tag = node.tagName.toUpperCase();
      if (!ALLOWED.has(tag)) return document.createTextNode(node.textContent || '');
      if (tag === 'BR' || tag === 'HR') return document.createElement(tag.toLowerCase());

      var el = document.createElement(tag.toLowerCase());

      // 通用屬性
      if (node.className) el.className = node.className;
      if (node.title) el.title = node.title;

      // 超連結屬性
      if (tag === 'A') {
        var href = node.getAttribute('href');
        var tgt = node.getAttribute('target');
        var rel = node.getAttribute('rel');

        if (href) el.setAttribute('href', href);
        if (tgt) el.setAttribute('target', tgt);
        // 若 target=_blank 且沒 rel，補上安全屬性
        if (tgt === '_blank' && !rel) rel = 'noopener noreferrer';
        if (rel) el.setAttribute('rel', rel);
      }

      // 表格欄位對齊（Parsedown 生成的 style="text-align: ..."）
      if (tag === 'TH' || tag === 'TD') {
        var style = node.getAttribute('style');
        if (style) el.setAttribute('style', style);
      }

      // 程式碼區塊的語言 class（如 language-php）
      if (tag === 'CODE') {
        var cls = node.getAttribute('class');
        if (cls) el.setAttribute('class', cls);
      }

      return el;
    }
    return document.createTextNode('');
  }

  function prepareTyping(srcParent, dstParent, queue) {
    Array.from(srcParent.childNodes).forEach(function (src) {
      if (src.nodeType === Node.TEXT_NODE) {
        var t = document.createTextNode('');
        dstParent.appendChild(t);
        var text = src.textContent || '';
        if (text.length) queue.push({ node: t, text: text });
      } else if (src.nodeType === Node.ELEMENT_NODE) {
        var cloned = cloneShallow(src);
        dstParent.appendChild(cloned);

        if (cloned.nodeType === Node.TEXT_NODE) {
          var txt = cloned.textContent || '';
          if (txt.length) queue.push({ node: cloned, text: txt });
        } else if (cloned.tagName && cloned.tagName.toUpperCase() === 'BR') {
          // no-op
        } else {
          prepareTyping(src, cloned, queue);
        }
      }
    });
  }

  function delay(ms) {
    // 加入 ±jitter 的隨機抖動，讓節奏更自然
    var j = CFG.typing_jitter_ms | 0;
    if (j > 0) {
      var d = (Math.random() * (2 * j)) - j; // [-j, +j]
      ms = Math.max(0, ms + d);
    }
    return new Promise(function (r) { setTimeout(r, ms); });
  }

  async function typeQueue(queue, target) {
    if (CFG.typing_disabled) {
      // 直接一次性輸出（無打字動畫）
      queue.forEach(function (item) { item.node.textContent = item.text; });
      return;
    }

    var cursor = document.createElement('span');
    cursor.className = 'moe-typing-cursor';
    target.appendChild(cursor);

    try {
      for (var q = 0; q < queue.length; q++) {
        var item = queue[q];
        var chars = Array.from(item.text);
        for (var i = 0; i < chars.length; i++) {
          item.node.textContent += chars[i];
          await delay(CFG.typing_ms);
        }
      }
    } finally {
      if (cursor && cursor.parentNode) cursor.parentNode.removeChild(cursor);
    }
  }

  // ---- 主程式：打字機效果 ----
  var __ranTyping = false;
  function runTyping() {
    if (__ranTyping) return; // 防止重入
    __ranTyping = true;

    var srcTpl = document.getElementById('moe-ans-source');
    var target = document.getElementById('moe-ans-target');
    if (!srcTpl || !target) return;

    // 保險：清空 target，避免重複打字
    target.textContent = '';

    var sourceRoot = document.createElement('div');
    sourceRoot.innerHTML = srcTpl.innerHTML;
    var textLen = (sourceRoot.textContent || '').length;
    if (!CFG.typing_disabled && CFG.typing_max_chars > 0 && textLen > CFG.typing_max_chars) {
      CFG.typing_disabled = true;
    }

    var queue = [];
    prepareTyping(sourceRoot, target, queue);

    if (queue.length === 0) {
      var fallback =
        typeof CFG.typing_fallback === 'string' && CFG.typing_fallback.length
          ? CFG.typing_fallback
          : '抱歉,目前無法取得 AI 回答,請稍後再試。';
      target.innerHTML = '<p>' + fallback + '</p>';
      return;
    }
    typeQueue(queue, target);
  }

  // ---- 關閉按鈕 ----
  var __boundClose = false;
  function bindClose() {
    if (__boundClose) return;
    __boundClose = true;

    var btn = document.getElementById('moe-close-btn');
    if (!btn) return;

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      try {
        window.close();
        setTimeout(function () {
          if (history.length > 1) history.back();
          else window.location.href = btn.getAttribute('href') || '#';
        }, 80);
      } catch (e2) { /* noop */ }
    });
  }

  // ---- 啟動（不需要任何 inline script）----
  function start() {
    runTyping();
    bindClose();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    // DOM 已就緒
    start();
  }
})();
