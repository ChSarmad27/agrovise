/**
 * AGROVISE - Numeric input auto-formatting
 * Inserts the dashes automatically while the user types:
 *   phone -> 0000-0000000          (11 digits, groups 4-7)
 *   cnic  -> 00000-0000000-0       (13 digits, groups 5-7-1)
 * Attaches itself to every input named "phone" or "cnic", and also
 * reformats any pre-filled value (e.g. legacy numbers saved without dashes).
 */
(function () {
    var SPECS = {
        phone: { groups: [4, 7],    max: 11 },
        cnic:  { groups: [5, 7, 1], max: 13 }
    };

    function fmt(digits, spec) {
        var out = '', pos = 0;
        for (var i = 0; i < spec.groups.length && pos < digits.length; i++) {
            var take = digits.substr(pos, spec.groups[i]);
            out += (i ? '-' : '') + take;
            pos += take.length;
        }
        return out;
    }

    function attach(input, spec) {
        var maxLen = spec.max + spec.groups.length - 1;   // digits + dashes
        input.setAttribute('maxlength', maxLen);
        input.setAttribute('inputmode', 'numeric');

        input.addEventListener('input', function () {
            var caret = input.selectionStart || 0;
            var digitsBefore = input.value.slice(0, caret).replace(/\D/g, '').length;
            var digits = input.value.replace(/\D/g, '').slice(0, spec.max);
            input.value = fmt(digits, spec);

            // put the caret back after the same number of digits
            var newCaret = 0, seen = 0;
            while (newCaret < input.value.length && seen < digitsBefore) {
                if (/\d/.test(input.value.charAt(newCaret))) seen++;
                newCaret++;
            }
            try { input.setSelectionRange(newCaret, newCaret); } catch (e) {}
        });

        // reformat any existing value (legacy data without dashes)
        if (input.value) {
            var d = input.value.replace(/\D/g, '').slice(0, spec.max);
            if (d.length) input.value = fmt(d, spec);
        }
    }

    function init() {
        document.querySelectorAll('input[name="phone"]').forEach(function (el) { attach(el, SPECS.phone); });
        document.querySelectorAll('input[name="cnic"]').forEach(function (el) { attach(el, SPECS.cnic); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
