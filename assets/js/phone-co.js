/**
 * Celulares Colombia (10 dígitos). Acepta pegar/autofill con +57 / 57 / 0057.
 */
(function (global) {
    'use strict';

    var MAX_LEN = 10;

    function digitsOnly(raw) {
        return String(raw == null ? '' : raw).replace(/\D/g, '');
    }

    /**
     * @param {string} raw
     * @returns {string} solo dígitos, máx. 10 (sin indicativo 57)
     */
    function normalize(raw) {
        var d = digitsOnly(raw);

        if (d.indexOf('00') === 0) {
            d = d.slice(2);
        }

        // 573001234567 → 3001234567 (también si viene basura extra)
        while (d.indexOf('57') === 0 && d.length >= 12) {
            d = d.slice(2);
        }

        return d.slice(0, MAX_LEN);
    }

    function isValid(raw) {
        return /^\d{10}$/.test(normalize(raw));
    }

    /**
     * Enlaza input: paste con preventDefault (evita maxlength/truncado) + input.
     * @param {HTMLInputElement|null} el
     * @param {{ onChange?: (digits: string, el: HTMLInputElement) => void }} [opts]
     */
    function bind(el, opts) {
        if (!el) {
            return;
        }

        var onChange = opts && typeof opts.onChange === 'function' ? opts.onChange : null;

        function apply(raw) {
            var next = normalize(raw);
            if (el.value !== next) {
                el.value = next;
            }
            if (onChange) {
                onChange(next, el);
            }
            return next;
        }

        el.addEventListener('paste', function (e) {
            e.preventDefault();
            var clip = (e.clipboardData || global.clipboardData);
            var text = clip && clip.getData ? clip.getData('text') : '';
            apply(text);
        });

        el.addEventListener('input', function () {
            apply(el.value);
        });

        // Autofill iOS a veces no dispara paste; normaliza en blur también
        el.addEventListener('blur', function () {
            apply(el.value);
        });
    }

    global.PhoneCo = {
        MAX_LEN: MAX_LEN,
        digitsOnly: digitsOnly,
        normalize: normalize,
        isValid: isValid,
        bind: bind
    };
})(typeof window !== 'undefined' ? window : this);
