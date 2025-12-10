(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
      fn();
    }
  }

  function initFeedback() {
    var globalConfig = window.MoelogAIQnA || {};
    var config = globalConfig.feedback;
    if (!config) return;

    var card = document.getElementById('moe-feedback-card');
    if (!card) return;

    var buttons = card.querySelectorAll('.moe-feedback-btn');
    var messageEl = document.getElementById('moe-feedback-message');
    var reportBox = document.getElementById('moe-feedback-report');
    var textarea = card.querySelector('textarea[name="moe-feedback-text"]');
    var cancelBtn = card.querySelector('[data-feedback-cancel]');
    var submitBtn = card.querySelector('[data-feedback-submit]');
    var statEls = document.querySelectorAll('[data-stat]');

    var storage = (function () {
      try {
        var testKey = '__moe_feedback';
        window.localStorage.setItem(testKey, '1');
        window.localStorage.removeItem(testKey);
        return window.localStorage;
      } catch (e) {
        return null;
      }
    })();

    var keySuffix = config.questionHash || (config.postId ? String(config.postId) : '');
    var viewKey = keySuffix ? 'moe_aiqna_view_' + keySuffix : '';
    var voteKey = keySuffix ? 'moe_aiqna_vote_' + keySuffix : '';
    var reportKey = keySuffix ? 'moe_aiqna_report_' + keySuffix : '';
    var dict = config.i18n || {};
    var t = function (key, fallback) {
      if (fallback === void 0) fallback = '';
      return typeof dict[key] !== 'undefined' ? dict[key] : fallback;
    };

    requestBootstrap()
      .then(function (data) {
        config.nonce = data.nonce;
        config.stats = data.stats || config.stats;
        if (config.stats) {
          updateStats(config.stats);
        }
        initialize();
      })
      .catch(function (err) {
        if (err) console.error(err);
        setMessage(err && err.message ? err.message : t('failed'), 'error');
      });

    function initialize() {
      bindEvents();
      applyLocalState();
      recordView();
    }

    function bindEvents() {
      buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var action = btn.getAttribute('data-action');
          if (action === 'like' || action === 'dislike') {
            handleVote(action);
          } else if (action === 'report') {
            toggleReport(reportBox.hidden);
          }
        });
      });

      if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
          toggleReport(false);
        });
      }

      if (submitBtn) {
        submitBtn.addEventListener('click', handleReport);
      }
    }

    function applyLocalState() {
      if (storage && voteKey) {
        var picked = storage.getItem(voteKey);
        if (picked) {
          markActive(picked);
        }
      }

      if (storage && reportKey && storage.getItem(reportKey)) {
        setMessage(t('reportSeen'), 'success');
      }
    }

    function requestBootstrap() {
      if (!config.postId) {
        return Promise.reject(new Error(t('failed')));
      }
      var formData = new FormData();
      formData.append('action', 'moelog_aiqna_feedback_bootstrap');
      formData.append('post_id', config.postId);
      if (typeof config.questionHash !== 'undefined') {
        formData.append('question_hash', config.questionHash);
      }
      formData.append('question', config.question || '');

      return fetch(config.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData,
      })
        .then(function (resp) {
          return resp.json();
        })
        .then(function (res) {
          if (res && res.success && res.data) {
            return res.data;
          }
          var msg =
            (res && res.data && res.data.message) ||
            (res && res.message) ||
            t('failed');
          throw new Error(msg);
        });
    }

    function setMessage(text, state) {
      if (!messageEl) return;
      messageEl.textContent = text || '';
      if (state) {
        messageEl.dataset.state = state;
      } else {
        delete messageEl.dataset.state;
      }
    }

    function updateStats(newStats) {
      if (!newStats) return;
      statEls.forEach(function (el) {
        var key = el.getAttribute('data-stat');
        if (key && typeof newStats[key] !== 'undefined') {
          el.textContent = newStats[key];
        }
      });
    }

    function markActive(type) {
      buttons.forEach(function (btn) {
        var action = btn.getAttribute('data-action');
        if (action === 'like' || action === 'dislike') {
          var isActive = action === type;
          btn.classList.toggle('is-active', isActive);
          btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        }
      });
    }

    function sendRequest(action, payload) {
      var formData = new FormData();
      formData.append('action', action);
      formData.append('nonce', config.nonce);
      if (config.questionHash !== undefined && config.questionHash !== null) {
        formData.append('question_hash', config.questionHash);
      }
      Object.keys(payload || {}).forEach(function (key) {
        formData.append(key, payload[key]);
      });
      return fetch(config.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData,
      })
        .then(function (resp) {
          return resp.json();
        })
        .catch(function () {
          return { success: false, data: {}, message: t('unexpected') };
        });
    }

    function recordView() {
      if (!config.postId || !config.nonce) return;

      var hasViewed = storage && viewKey && storage.getItem(viewKey);
      var payload = {
        post_id: config.postId,
        increment: !hasViewed,
      };

      sendRequest('moelog_aiqna_record_view', payload).then(function (res) {
        if (res && res.success && res.data && res.data.stats) {
          updateStats(res.data.stats);
          if (!hasViewed && storage && viewKey) {
            storage.setItem(viewKey, '1');
          }
        }
      });
    }

    function handleVote(type) {
      if (!config.postId || !config.nonce) return;

      var previousVote = storage && voteKey ? storage.getItem(voteKey) : '';
      if (previousVote === type) {
        setMessage(t('alreadyVoted'));
        return;
      }

      setMessage(t('submitting'));

      sendRequest('moelog_aiqna_vote', {
        post_id: config.postId,
        vote: type,
        previous_vote: previousVote || '',
      }).then(function (res) {
        if (res && res.success) {
          updateStats(res.data && res.data.stats);
          markActive(res.data && res.data.vote);
          setMessage(t('thanks'), 'success');
          if (storage && voteKey) {
            storage.setItem(voteKey, type);
          }
        } else {
          var msg =
            (res && res.data && res.data.message) ||
            (res && res.message) ||
            t('failed');
          setMessage(msg, 'error');
        }
      });
    }

    function toggleReport(show) {
      if (!reportBox) return;
      reportBox.hidden = !show;
      if (!show) {
        if (textarea) {
          textarea.value = '';
        }
        setMessage('');
      } else if (textarea) {
        textarea.focus();
      }
    }

    function handleReport() {
      if (!config.postId || !textarea || !config.nonce) return;
      var value = textarea.value.trim();
      if (value.length < 3) {
        setMessage(t('needMore'), 'error');
        return;
      }

      // 🔒 檢查訊息長度限制
      if (value.length > 300) {
        setMessage(t('failed'), 'error');
        return;
      }

      setMessage(t('submitting'));
      if (submitBtn) {
        submitBtn.disabled = true;
      }
      
      // 🔒 蜜罐欄位：讀取隱藏欄位的值（正常應為空）
      var honeypot = document.getElementById('moe-hp-field');
      var hpValue = honeypot ? honeypot.value : '';
      
      sendRequest('moelog_aiqna_report_issue', {
        post_id: config.postId,
        question: config.question || '',
        message: value,
        website: hpValue  // 蜜罐欄位
      })
        .then(function (res) {
          if (res && res.success) {
            var msg =
              (res.data && res.data.message) || t('reportThanks');
            setMessage(msg, 'success');
            toggleReport(false);
            if (storage && reportKey) {
              storage.setItem(reportKey, Date.now().toString());
            }
          } else {
            var errorMsg =
              (res && res.data && res.data.message) ||
              (res && res.message) ||
              t('failed');
            setMessage(errorMsg, 'error');
          }
        })
        .finally(function () {
          if (submitBtn) {
            submitBtn.disabled = false;
          }
        });
    }
  }

  ready(initFeedback);
})();


