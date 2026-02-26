/**
 * CallismartDatePicker
 * A fully accessible, multi-instance datetime picker.
 * Mounts on <input type="date"> and <input type="datetime-local">
 * or any input with [smliser-date-picker] canonical attribute.
 *
 * @version 2.0.0
 * @license MIT
 *
 * ─── Canonical Attribute API ──────────────────────────────────────────────────
 *  smliser-date-picker="date"                  → date-only picker
 *  smliser-date-picker="datetime"              → date + time picker
 *  smliser-date-picker-min="YYYY-MM-DD"        → minimum selectable date
 *  smliser-date-picker-max="YYYY-MM-DD"        → maximum selectable date
 *  smliser-date-picker-disabled-days="0,6"     → comma-separated weekday indices (0=Sun)
 *  smliser-date-picker-format="MM/DD/YYYY"     → display format (tokens: YYYY MM DD HH mm)
 *  smliser-date-picker-theme="dark"            → force theme ("light"|"dark")
 *  smliser-date-picker-week-start="1"          → 0=Sunday, 1=Monday (default 0)
 *  smliser-date-picker-time-step="15"          → minute step for time picker (default 1)
 *  smliser-date-picker-inline="true"           → render inline (no popup)
 *  smliser-date-picker-close-on-select="false" → keep open after date select
 *
 * ─── Programmatic API ─────────────────────────────────────────────────────────
 *  const picker = new CallismartDatePicker(element, options)
 *  picker.getValue()                → ISO string of current value
 *  picker.setValue(isoString)       → set value programmatically
 *  picker.getDate()                 → Date object
 *  picker.setDate(dateObj)          → set from Date object
 *  picker.open()                    → show popup
 *  picker.close()                   → hide popup
 *  picker.toggle()                  → toggle popup
 *  picker.clear()                   → clear value
 *  picker.destroy()                 → remove picker, restore original input
 *  picker.setMin(isoString)         → set min date
 *  picker.setMax(isoString)         → set max date
 *  picker.setDisabledDays(arr)      → set disabled weekday indices
 *  picker.setDisabledDates(arr)     → set specific disabled dates (ISO strings)
 *  picker.setTheme(name)            → "light"|"dark"|"auto"
 *  picker.goToMonth(year, mon)      → navigate calendar (mon: 0-11)
 *  picker.setFixedHeaderOffset(px)  → update fixed-header clearance at runtime
 *  picker.on(event, cb)             → subscribe to events
 *  picker.off(event, cb)            → unsubscribe
 *
 * ─── Events ───────────────────────────────────────────────────────────────────
 *  'change'   → { value, date }   fired when value changes
 *  'open'     → {}                fired when popup opens
 *  'close'    → {}                fired when popup closes
 *  'navigate' → { year, month }   fired on month navigation
 *  'clear'    → {}                fired when cleared
 *
 * ─── Static API ───────────────────────────────────────────────────────────────
 *  CallismartDatePicker.mountAll(selector?, options?)  → mount on all matching inputs
 *  CallismartDatePicker.setGlobalDefaults(options)     → set default options for new instances
 */

class CallismartDatePicker {

    // ═══════════════════════════════════════════════════════════════════════════
    // Static private — constants
    // ═══════════════════════════════════════════════════════════════════════════

    static #DAYS_SHORT = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

    static #DAYS_FULL = [
        'Sunday', 'Monday', 'Tuesday', 'Wednesday',
        'Thursday', 'Friday', 'Saturday',
    ];

    static #MONTHS_FULL = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    // ═══════════════════════════════════════════════════════════════════════════
    // Static private — module-level state
    // ═══════════════════════════════════════════════════════════════════════════

    static #instanceCount = 0;
    static #globalDefaults = {};

    // ═══════════════════════════════════════════════════════════════════════════
    // Static private — pure utility methods
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Zero-pad a number to two digits.
     * @param {number} n
     * @returns {string}
     */
    static #pad(n) {
        return String(n).padStart(2, '0');
    }

    /**
     * querySelectorAll shorthand that always returns an Array.
     * @param {string}            sel
     * @param {Document|Element}  ctx
     * @returns {Element[]}
     */
    static #$$(sel, ctx = document) {
        return [...ctx.querySelectorAll(sel)];
    }

    /**
     * Parse an ISO date or datetime string.
     * Appends T00:00:00 to date-only strings to prevent UTC-offset shift.
     * @param {string} str
     * @returns {Date|null}
     */
    static #parseISO(str) {
        if (!str) return null;
        const d = new Date(str.includes('T') ? str : `${str}T00:00:00`);
        return isNaN(d) ? null : d;
    }

    /**
     * Serialize a Date to YYYY-MM-DD or YYYY-MM-DDTHH:mm.
     * @param {Date}    date
     * @param {boolean} withTime
     * @returns {string}
     */
    static #toISO(date, withTime = false) {
        if (!date) return '';
        const p = CallismartDatePicker.#pad;
        const y = date.getFullYear();
        const m = p(date.getMonth() + 1);
        const d = p(date.getDate());
        if (!withTime) return `${y}-${m}-${d}`;
        return `${y}-${m}-${d}T${p(date.getHours())}:${p(date.getMinutes())}`;
    }

    /**
     * Format a Date for the visible display input.
     * When no format string is supplied a human-readable default is used.
     * @param {Date}        date
     * @param {string|null} fmt      Token string e.g. "MM/DD/YYYY"
     * @param {boolean}     withTime
     * @returns {string}
     */
    static #formatDisplay(date, fmt, withTime) {
        if (!date) return '';
        const p = CallismartDatePicker.#pad;
        const M = CallismartDatePicker.#MONTHS_FULL;
        if (!fmt) {
            return withTime
                ? `${M[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()} ${p(date.getHours())}:${p(date.getMinutes())}`
                : `${M[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
        }
        return fmt
            .replace('YYYY', date.getFullYear())
            .replace('MM', p(date.getMonth() + 1))
            .replace('DD', p(date.getDate()))
            .replace('HH', p(date.getHours()))
            .replace('mm', p(date.getMinutes()));
    }

    /**
     * Return true when two Date values represent the same calendar day.
     * Safely returns false for null/undefined arguments.
     * @param {Date|null} a
     * @param {Date|null} b
     * @returns {boolean}
     */
    static #isSameDay(a, b) {
        return !!(
            a && b &&
            a.getFullYear() === b.getFullYear() &&
            a.getMonth() === b.getMonth() &&
            a.getDate() === b.getDate()
        );
    }

    /**
     * Return a Date set to the first day of the given year/month.
     * @param {number} y
     * @param {number} m  0-based month index
     * @returns {Date}
     */
    static #startOfMonth(y, m) {
        return new Date(y, m, 1);
    }

    /**
     * Return the number of days in the given year/month.
     * @param {number} y
     * @param {number} m  0-based month index
     * @returns {number}
     */
    static #daysInMonth(y, m) {
        return new Date(y, m + 1, 0).getDate();
    }

    /**
     * Snapshot all computed style properties that define the visual appearance
     * of a form input, so the replacement display input inherits them exactly.
     * @param {HTMLElement} el
     * @returns {Object.<string,string>}
     */
    static #snapshotInputStyles(el) {
        const cs = window.getComputedStyle(el);
        return {
            fontFamily: cs.fontFamily,
            fontSize: cs.fontSize,
            fontWeight: cs.fontWeight,
            lineHeight: cs.lineHeight,
            letterSpacing: cs.letterSpacing,
            color: cs.color,
            backgroundColor: cs.backgroundColor,
            borderTopWidth: cs.borderTopWidth,
            borderRightWidth: cs.borderRightWidth,
            borderBottomWidth: cs.borderBottomWidth,
            borderLeftWidth: cs.borderLeftWidth,
            borderTopStyle: cs.borderTopStyle,
            borderRightStyle: cs.borderRightStyle,
            borderBottomStyle: cs.borderBottomStyle,
            borderLeftStyle: cs.borderLeftStyle,
            borderTopColor: cs.borderTopColor,
            borderRightColor: cs.borderRightColor,
            borderBottomColor: cs.borderBottomColor,
            borderLeftColor: cs.borderLeftColor,
            borderTopLeftRadius: cs.borderTopLeftRadius,
            borderTopRightRadius: cs.borderTopRightRadius,
            borderBottomLeftRadius: cs.borderBottomLeftRadius,
            borderBottomRightRadius: cs.borderBottomRightRadius,
            paddingTop: cs.paddingTop,
            paddingRight: cs.paddingRight,
            paddingBottom: cs.paddingBottom,
            paddingLeft: cs.paddingLeft,
            height: cs.height,
            minHeight: cs.minHeight,
            width: cs.width,
            minWidth: cs.minWidth,
            maxWidth: cs.maxWidth,
            boxSizing: cs.boxSizing,
            boxShadow: cs.boxShadow,
            outline: cs.outline,
        };
    }

    /**
     * Write a style snapshot onto an input element, then add extra right-padding
     * to reserve space for the calendar icon and clear button.
     * @param {HTMLInputElement}       input
     * @param {Object.<string,string>} snapshot
     */
    static #applySnapshotToInput(input, snapshot) {
        Object.assign(input.style, snapshot);
        // 52px = 24px icon + 20px clear-btn + gaps — added on top of the original padding-right
        const origPR = parseFloat(snapshot.paddingRight) || 0;
        input.style.paddingRight = `${origPR + 52}px`;
    }

    /**
     * Return the calendar SVG markup string.
     * @returns {string}
     */
    static #calendarIconSVG() {
        return `<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15"
      viewBox="0 0 24 24" fill="none" stroke="currentColor"
      stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
      aria-hidden="true">
      <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
      <line x1="16" y1="2" x2="16" y2="6"/>
      <line x1="8"  y1="2" x2="8"  y2="6"/>
      <line x1="3"  y1="10" x2="21" y2="10"/>
    </svg>`;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Instance private fields
    // ═══════════════════════════════════════════════════════════════════════════

    // Core state
    #id;
    #el;
    #opts;
    #withTime;
    #listeners = {};
    #isOpen = false;
    #selectedDate = null;
    #viewYear = null;
    #viewMonth = null;

    // DOM references
    #wrapper;
    #displayInput;
    #iconBtn;
    #clearBtn;
    #popup;
    #grid;
    #monthSel;
    #yearSel;
    #hourInput;
    #minInput;

    // Bound handlers kept for removeEventListener
    #onDisplayClick;
    #onDisplayKeydown;
    #onDocClick;
    #onScroll;
    #mq;
    #onMqChange;

    // ═══════════════════════════════════════════════════════════════════════════
    // Constructor
    // ═══════════════════════════════════════════════════════════════════════════

    constructor(element, options = {}) {
        if (!(element instanceof HTMLElement)) {
            throw new Error('CallismartDatePicker: element must be an HTMLElement');
        }
        if (element.hasAttribute('data-cdp-id')) {
            console.warn('CallismartDatePicker: already mounted on this element.');
            return;
        }

        this.#id = `cdp-${++CallismartDatePicker.#instanceCount}`;
        this.#el = element;

        // Snapshot BEFORE DOM mutation so we capture the host page's styling
        const snapshot = CallismartDatePicker.#snapshotInputStyles(element);

        // Option precedence: built-in defaults → global defaults → attribute API → constructor arg
        this.#opts = Object.assign(
            {
                mode: 'date',
                min: null,
                max: null,
                disabledDays: [],
                disabledDates: [],
                format: null,
                theme: 'auto',
                weekStart: 0,
                timeStep: 1,
                inline: false,
                closeOnSelect: true,
                yearRange: 100,
                fixedHeaderOffset: 100,
            },
            CallismartDatePicker.#globalDefaults,
            this.#readAttrs(element),
            options,
        );

        this.#withTime = this.#opts.mode === 'datetime' || element.type === 'datetime-local';

        this.#init(snapshot);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Instance private — attribute parsing
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Read all smliser-date-picker-* attributes from the element and return
     * a partial options object.
     * @param {HTMLElement} el
     * @returns {Object}
     */
    #readAttrs(el) {
        const o = {};
        const get = (name) => el.getAttribute(`smliser-date-picker-${name}`);

        const mode = el.getAttribute('smliser-date-picker');
        if (mode) o.mode = mode === 'datetime' ? 'datetime' : 'date';

        if (get('min')) o.min = get('min');
        if (get('max')) o.max = get('max');
        if (get('format')) o.format = get('format');
        if (get('theme')) o.theme = get('theme');
        if (get('week-start')) o.weekStart = parseInt(get('week-start'), 10);
        if (get('time-step')) o.timeStep = parseInt(get('time-step'), 10);
        if (get('inline')) o.inline = get('inline') === 'true';

        const cos = get('close-on-select');
        if (cos !== null) o.closeOnSelect = cos !== 'false';

        if (get('disabled-days'))
            o.disabledDays = get('disabled-days').split(',').map(Number);

        return o;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Instance private — DOM construction
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Build and insert all DOM nodes, then bind events.
     * @param {Object.<string,string>} snapshot  Computed-style snapshot
     */
    #init(snapshot) {
        const el = this.#el;

        el.setAttribute('autocomplete', 'off');
        el.setAttribute('readonly', 'true');
        el.setAttribute('aria-haspopup', 'dialog');
        el.setAttribute('aria-expanded', 'false');
        el.setAttribute('data-cdp-id', this.#id);

        // ── Wrapper ──────────────────────────────────────────────────────────────
        this.#wrapper = document.createElement('div');
        this.#wrapper.className = 'cdp-wrapper';
        this.#wrapper.setAttribute('data-cdp-theme', this.#opts.theme);
        el.parentNode.insertBefore(this.#wrapper, el);
        this.#wrapper.appendChild(el);

        // ── Display input ────────────────────────────────────────────────────────
        this.#displayInput = document.createElement('input');
        this.#displayInput.type = 'text';
        this.#displayInput.className = 'cdp-display-input';
        this.#displayInput.setAttribute('aria-label', el.getAttribute('aria-label') || el.getAttribute('placeholder') || 'Date picker');
        this.#displayInput.setAttribute('aria-controls', `${this.#id}-popup`);
        this.#displayInput.setAttribute('aria-expanded', 'false');
        this.#displayInput.setAttribute('aria-haspopup', 'dialog');
        this.#displayInput.setAttribute('role', 'combobox');
        this.#displayInput.setAttribute('aria-autocomplete', 'none');
        this.#displayInput.readOnly = true;
        this.#displayInput.placeholder = el.placeholder || (this.#withTime ? 'Select date & time' : 'Select date');

        // Move id so <label for="..."> keeps working
        if (el.id) {
            this.#displayInput.id = el.id;
            el.removeAttribute('id');
        }

        CallismartDatePicker.#applySnapshotToInput(this.#displayInput, snapshot);
        this.#wrapper.insertBefore(this.#displayInput, el);

        // ── Calendar icon (decorative; pointer-events:none in CSS) ───────────────
        this.#iconBtn = this.#makeButton(
            'cdp-icon-btn',
            CallismartDatePicker.#calendarIconSVG(),
            'Open date picker',
        );
        this.#iconBtn.setAttribute('tabindex', '-1');
        this.#iconBtn.setAttribute('aria-hidden', 'true');
        this.#wrapper.appendChild(this.#iconBtn);

        // ── Clear button ─────────────────────────────────────────────────────────
        this.#clearBtn = this.#makeButton('cdp-clear-btn', '&times;', 'Clear date');
        this.#clearBtn.setAttribute('tabindex', '-1');
        this.#clearBtn.style.display = 'none';
        this.#wrapper.appendChild(this.#clearBtn);

        // ── Popup ─────────────────────────────────────────────────────────────────
        this.#popup = document.createElement('div');
        this.#popup.className = 'cdp-popup';
        this.#popup.id = `${this.#id}-popup`;
        this.#popup.setAttribute('role', 'dialog');
        this.#popup.setAttribute('aria-modal', 'true');
        this.#popup.setAttribute('aria-label', 'Date picker dialog');
        this.#popup.hidden = true;

        if (this.#opts.inline) {
            this.#wrapper.appendChild(this.#popup);
            this.#wrapper.classList.add('cdp-inline');
        } else {
            document.body.appendChild(this.#popup);
        }

        // Hydrate initial value from the original input
        if (el.value) {
            const d = CallismartDatePicker.#parseISO(el.value);
            if (d) this.#setSelectedDate(d, false);
        }

        if (this.#opts.inline) {
            this.#renderPopupContent();
            this.#popup.hidden = false;
        }

        this.#bindEvents();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Instance private — event binding
    // ═══════════════════════════════════════════════════════════════════════════

    #bindEvents() {
        this.#onDisplayClick = () => this.toggle();
        this.#displayInput.addEventListener('click', this.#onDisplayClick);
        this.#iconBtn.addEventListener('click', this.#onDisplayClick);

        this.#clearBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.clear();
        });

        this.#onDisplayKeydown = (e) => this.#handleDisplayKeydown(e);
        this.#displayInput.addEventListener('keydown', this.#onDisplayKeydown);

        this.#onDocClick = (e) => {
            if (
                !this.#popup.hidden &&
                !this.#wrapper.contains(e.target) &&
                !this.#popup.contains(e.target)
            ) { this.close(); }
        };
        document.addEventListener('mousedown', this.#onDocClick);

        this.#onScroll = () => { if (!this.#opts.inline) this.#position(); };
        window.addEventListener('scroll', this.#onScroll, true);
        window.addEventListener('resize', this.#onScroll);

        if (this.#opts.theme === 'auto') {
            this.#mq = window.matchMedia('(prefers-color-scheme: dark)');
            this.#onMqChange = () => this.#applyTheme();
            this.#mq.addEventListener('change', this.#onMqChange);
            this.#applyTheme();
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Instance private — keyboard handlers
    // ═══════════════════════════════════════════════════════════════════════════

    #handleDisplayKeydown(e) {
        switch (e.key) {
            case 'Enter': case ' ': e.preventDefault(); this.toggle(); break;
            case 'Escape': this.close(); break;
            case 'Tab':
                if (!this.#popup.hidden) this.#trapFocus(e);
                break;
        }
    }

    #handleDayKeydown(e, date) {
        const SOM = CallismartDatePicker.#startOfMonth;
        const DIM = CallismartDatePicker.#daysInMonth;
        let target = null;

        switch (e.key) {
            case 'ArrowRight': target = new Date(date); target.setDate(date.getDate() + 1); break;
            case 'ArrowLeft': target = new Date(date); target.setDate(date.getDate() - 1); break;
            case 'ArrowDown': target = new Date(date); target.setDate(date.getDate() + 7); break;
            case 'ArrowUp': target = new Date(date); target.setDate(date.getDate() - 7); break;
            case 'Home': target = SOM(this.#viewYear, this.#viewMonth); break;
            case 'End': target = new Date(this.#viewYear, this.#viewMonth, DIM(this.#viewYear, this.#viewMonth)); break;
            case 'PageUp': this.#navigate(-1); return;
            case 'PageDown': this.#navigate(1); return;
            case 'Enter': case ' ':
                e.preventDefault();
                this.#selectDate(date);
                return;
            case 'Escape':
                this.close();
                return;
            default: return;
        }

        e.preventDefault();
        if (!target) return;

        if (target.getMonth() !== this.#viewMonth || target.getFullYear() !== this.#viewYear) {
            this.#viewMonth = target.getMonth();
            this.#viewYear = target.getFullYear();
            this.#reRender();
        }
        const btn = this.#grid?.querySelector(`button[aria-label*="${target.getDate()},"]`);
        if (btn) btn.focus();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Instance private — popup rendering
    // ═══════════════════════════════════════════════════════════════════════════

    #renderPopupContent() {
        const ref = this.#selectedDate || new Date();
        this.#viewYear = this.#viewYear ?? ref.getFullYear();
        this.#viewMonth = this.#viewMonth ?? ref.getMonth();

        this.#popup.innerHTML = '';
        this.#popup.appendChild(this.#buildHeader());
        this.#popup.appendChild(this.#buildCalendar());
        if (this.#withTime) this.#popup.appendChild(this.#buildTimePicker());
        this.#popup.appendChild(this.#buildFooter());
    }

    /** Re-render only when the popup is visible (or permanently inline). */
    #reRender() {
        if (this.#popup.hidden && !this.#opts.inline) return;
        this.#renderPopupContent();
    }

    // ─── Header (month/year navigation) ─────────────────────────────────────────

    #buildHeader() {
        const header = document.createElement('div');
        header.className = 'cdp-header';

        const prevBtn = this.#makeButton('cdp-nav-btn cdp-prev', '&#8249;', 'Previous month', () => this.#navigate(-1));
        const nextBtn = this.#makeButton('cdp-nav-btn cdp-next', '&#8250;', 'Next month', () => this.#navigate(1));

        const title = document.createElement('div');
        title.className = 'cdp-header-title';

        // Month <select>
        this.#monthSel = document.createElement('select');
        this.#monthSel.className = 'cdp-month-select';
        this.#monthSel.setAttribute('aria-label', 'Month');
        CallismartDatePicker.#MONTHS_FULL.forEach((name, i) => {
            const opt = document.createElement('option');
            opt.value = i;
            opt.textContent = name;
            opt.selected = i === this.#viewMonth;
            this.#monthSel.appendChild(opt);
        });
        this.#monthSel.addEventListener('change', () => {
            this.#viewMonth = parseInt(this.#monthSel.value, 10);
            this.#reRender();
        });

        // Year <select>
        this.#yearSel = document.createElement('select');
        this.#yearSel.className = 'cdp-year-select';
        this.#yearSel.setAttribute('aria-label', 'Year');
        const curY = new Date().getFullYear();
        const minY = this.#opts.min
            ? CallismartDatePicker.#parseISO(this.#opts.min).getFullYear()
            : curY - this.#opts.yearRange;
        const maxY = this.#opts.max
            ? CallismartDatePicker.#parseISO(this.#opts.max).getFullYear()
            : curY + this.#opts.yearRange;
        for (let y = minY; y <= maxY; y++) {
            const opt = document.createElement('option');
            opt.value = y;
            opt.textContent = y;
            opt.selected = y === this.#viewYear;
            this.#yearSel.appendChild(opt);
        }
        this.#yearSel.addEventListener('change', () => {
            this.#viewYear = parseInt(this.#yearSel.value, 10);
            this.#reRender();
        });

        title.appendChild(this.#monthSel);
        title.appendChild(this.#yearSel);
        header.appendChild(prevBtn);
        header.appendChild(title);
        header.appendChild(nextBtn);
        return header;
    }

    // ─── Calendar grid ───────────────────────────────────────────────────────────

    #buildCalendar() {
        const DIM = CallismartDatePicker.#daysInMonth;
        const SOM = CallismartDatePicker.#startOfMonth;
        const DFULL = CallismartDatePicker.#DAYS_FULL;
        const DSHORT = CallismartDatePicker.#DAYS_SHORT;
        const MFULL = CallismartDatePicker.#MONTHS_FULL;
        const ISD = CallismartDatePicker.#isSameDay;

        const container = document.createElement('div');
        container.className = 'cdp-calendar';
        container.setAttribute('role', 'grid');
        container.setAttribute('aria-label', `${MFULL[this.#viewMonth]} ${this.#viewYear}`);

        const ws = this.#opts.weekStart;

        // Day-of-week header row
        const dayRow = document.createElement('div');
        dayRow.className = 'cdp-day-headers';
        dayRow.setAttribute('role', 'row');
        for (let i = 0; i < 7; i++) {
            const idx = (ws + i) % 7;
            const cell = document.createElement('div');
            cell.className = 'cdp-day-header';
            cell.setAttribute('role', 'columnheader');
            cell.setAttribute('aria-label', DFULL[idx]);
            cell.textContent = DSHORT[idx];
            dayRow.appendChild(cell);
        }
        container.appendChild(dayRow);

        // Day grid
        const grid = document.createElement('div');
        grid.className = 'cdp-grid';
        grid.setAttribute('role', 'rowgroup');

        const firstDay = SOM(this.#viewYear, this.#viewMonth);
        const totalDays = DIM(this.#viewYear, this.#viewMonth);
        const startOffset = (firstDay.getDay() - ws + 7) % 7;

        const today = new Date();
        const minDate = this.#opts.min ? CallismartDatePicker.#parseISO(this.#opts.min) : null;
        const maxDate = this.#opts.max ? CallismartDatePicker.#parseISO(this.#opts.max) : null;
        const disabledDatesSet = new Set(this.#opts.disabledDates || []);

        // Previous-month filler cells
        const prevMonthDays = DIM(this.#viewYear, this.#viewMonth - 1);
        for (let i = startOffset - 1; i >= 0; i--) {
            grid.appendChild(this.#makeOutsideCell(prevMonthDays - i));
        }

        // Current-month day buttons
        for (let d = 1; d <= totalDays; d++) {
            const date = new Date(this.#viewYear, this.#viewMonth, d);
            const iso = CallismartDatePicker.#toISO(date);
            const isToday = ISD(date, today);
            const isSelected = ISD(date, this.#selectedDate);
            const isDisabled =
                this.#opts.disabledDays.includes(date.getDay()) ||
                disabledDatesSet.has(iso) ||
                (minDate && date < minDate) ||
                (maxDate && date > maxDate);

            const cell = document.createElement('button');
            cell.type = 'button';
            cell.className = 'cdp-day';
            cell.textContent = d;

            if (isToday) cell.classList.add('cdp-today');
            if (isSelected) {
                cell.classList.add('cdp-selected');
                cell.setAttribute('aria-selected', 'true');
                cell.setAttribute('aria-current', 'date');
            }
            if (isDisabled) {
                cell.classList.add('cdp-disabled');
                cell.setAttribute('aria-disabled', 'true');
                cell.disabled = true;
            }

            cell.setAttribute('role', 'gridcell');
            cell.setAttribute('aria-label', `${DFULL[date.getDay()]}, ${MFULL[this.#viewMonth]} ${d}, ${this.#viewYear}`);
            cell.setAttribute('tabindex', (isSelected || (d === 1 && !this.#selectedDate)) ? '0' : '-1');

            if (!isDisabled) {
                cell.addEventListener('click', () => this.#selectDate(date));
                cell.addEventListener('keydown', (e) => this.#handleDayKeydown(e, date));
            }

            grid.appendChild(cell);
        }

        // Next-month filler cells
        const filled = startOffset + totalDays;
        const remainder = filled % 7 === 0 ? 0 : 7 - (filled % 7);
        for (let d = 1; d <= remainder; d++) grid.appendChild(this.#makeOutsideCell(d));

        container.appendChild(grid);
        this.#grid = grid;
        return container;
    }

    /**
     * Build a non-interactive filler cell for days outside the current month.
     * @param {number} day
     * @returns {HTMLDivElement}
     */
    #makeOutsideCell(day) {
        const cell = document.createElement('div');
        cell.className = 'cdp-day cdp-outside';
        cell.setAttribute('aria-hidden', 'true');
        cell.textContent = day;
        return cell;
    }

    // ─── Time picker ─────────────────────────────────────────────────────────────

    #buildTimePicker() {
        const wrap = document.createElement('div');
        wrap.className = 'cdp-time-section';

        const label = document.createElement('div');
        label.className = 'cdp-time-label';
        label.textContent = 'Time';
        label.setAttribute('aria-hidden', 'true');

        const controls = document.createElement('div');
        controls.className = 'cdp-time-controls';

        const curH = this.#selectedDate?.getHours() ?? 0;
        const curM = this.#selectedDate?.getMinutes() ?? 0;

        this.#hourInput = this.#buildTimeSpinner('Hour', 0, 23, curH, (v) => this.#updateTime(v, null));
        this.#minInput = this.#buildTimeSpinner('Minute', 0, 59, curM, (v) => this.#updateTime(null, v), this.#opts.timeStep);

        const sep = document.createElement('span');
        sep.className = 'cdp-time-sep';
        sep.textContent = ':';
        sep.setAttribute('aria-hidden', 'true');

        controls.appendChild(this.#hourInput.wrap);
        controls.appendChild(sep);
        controls.appendChild(this.#minInput.wrap);
        wrap.appendChild(label);
        wrap.appendChild(controls);
        return wrap;
    }

    /**
     * Build a labelled up/input/down spinner for hour or minute entry.
     * @param {string}   label
     * @param {number}   min
     * @param {number}   max
     * @param {number}   value    Initial value
     * @param {Function} onChange Called with the new numeric value
     * @param {number}   step
     * @returns {{ wrap: HTMLElement, input: HTMLInputElement }}
     */
    #buildTimeSpinner(label, min, max, value, onChange, step = 1) {
        const p = CallismartDatePicker.#pad;
        const wrap = document.createElement('div');
        wrap.className = 'cdp-spinner';

        const upBtn = this.#makeButton('cdp-spin-btn', '&#8963;', `Increment ${label}`);
        const downBtn = this.#makeButton('cdp-spin-btn', '&#8964;', `Decrement ${label}`);

        const input = document.createElement('input');
        input.type = 'number';
        input.className = 'cdp-time-input';
        input.min = min;
        input.max = max;
        input.step = step;
        input.value = p(value);
        input.setAttribute('aria-label', label);

        const get = () => parseInt(input.value, 10) || 0;

        upBtn.addEventListener('click', () => {
            let v = get() + step;
            if (v > max) v = min;
            input.value = p(v);
            onChange(v);
        });

        downBtn.addEventListener('click', () => {
            let v = get() - step;
            if (v < min) v = max;
            input.value = p(v);
            onChange(v);
        });

        input.addEventListener('change', () => {
            const v = Math.max(min, Math.min(max, get()));
            input.value = p(v);
            onChange(v);
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowUp') { e.preventDefault(); upBtn.click(); }
            if (e.key === 'ArrowDown') { e.preventDefault(); downBtn.click(); }
        });

        wrap.appendChild(upBtn);
        wrap.appendChild(input);
        wrap.appendChild(downBtn);
        return { wrap, input };
    }

    #updateTime(hours, minutes) {
        if (!this.#selectedDate) {
            const now = new Date();
            this.#selectedDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        }
        if (hours !== null) this.#selectedDate.setHours(hours);
        if (minutes !== null) this.#selectedDate.setMinutes(minutes);
        this.#commitValue();
    }

    // ─── Footer ──────────────────────────────────────────────────────────────────

    #buildFooter() {
        const footer = document.createElement('div');
        footer.className = 'cdp-footer';

        footer.appendChild(this.#makeButton('cdp-footer-btn cdp-today-btn', 'Today', null, () => {
            const today = new Date();
            this.#viewYear = today.getFullYear();
            this.#viewMonth = today.getMonth();
            this.#selectDate(today);
        }));

        footer.appendChild(this.#makeButton('cdp-footer-btn cdp-clear-footer-btn', 'Clear', null, () => this.clear()));

        if (this.#withTime) {
            footer.appendChild(this.#makeButton('cdp-footer-btn cdp-apply-btn', 'Apply', null, () => this.close()));
        }

        return footer;
    }

    // ─── Date selection & value commit ───────────────────────────────────────────

    #selectDate(date) {
        // Preserve current time component when switching days in datetime mode
        if (this.#withTime && this.#selectedDate) {
            date.setHours(this.#selectedDate.getHours());
            date.setMinutes(this.#selectedDate.getMinutes());
        }
        this.#setSelectedDate(date, true);
        if (!this.#withTime && this.#opts.closeOnSelect && !this.#opts.inline) {
            setTimeout(() => this.close(), 80);
        }
    }

    #setSelectedDate(date, emit = true) {
        this.#selectedDate = date;
        this.#viewYear = date.getFullYear();
        this.#viewMonth = date.getMonth();
        this.#commitValue(emit);
        if (!this.#popup.hidden) this.#reRender();
    }

    /**
     * Flush the selected date to both inputs and optionally dispatch events.
     * @param {boolean} emit
     */
    #commitValue(emit = true) {
        const iso = CallismartDatePicker.#toISO(this.#selectedDate, this.#withTime);
        this.#el.value = iso;
        this.#displayInput.value = CallismartDatePicker.#formatDisplay(
            this.#selectedDate, this.#opts.format, this.#withTime,
        );
        this.#clearBtn.style.display = this.#selectedDate ? '' : 'none';

        if (emit) {
            this.#el.dispatchEvent(new Event('change', { bubbles: true }));
            this.#emit('change', { value: iso, date: new Date(this.#selectedDate) });
        }
    }

    // ─── Month navigation ────────────────────────────────────────────────────────

    #navigate(dir) {
        this.#viewMonth += dir;
        if (this.#viewMonth > 11) { this.#viewMonth = 0; this.#viewYear++; }
        if (this.#viewMonth < 0) { this.#viewMonth = 11; this.#viewYear--; }
        this.#reRender();
        this.#emit('navigate', { year: this.#viewYear, month: this.#viewMonth });
    }

    // ─── Popup positioning ───────────────────────────────────────────────────────

    /**
     * Position the floating popup relative to the wrapper.
     * Flips upward when there is insufficient space below, while respecting
     * the configurable fixed-header clearance offset.
     */
    #position() {
        if (this.#opts.inline) return;

        const rect = this.#wrapper.getBoundingClientRect();
        const popH = this.#popup.offsetHeight || 380;
        const popW = this.#popup.offsetWidth || 300;
        const vpH = window.innerHeight;
        const vpW = window.innerWidth;
        const offset = this.#opts.fixedHeaderOffset;

        let top = rect.bottom + window.scrollY + 6;
        let left = rect.left + window.scrollX;

        const spaceBelow = vpH - rect.bottom;
        const spaceAbove = rect.top - offset;

        if (spaceBelow < popH && spaceAbove > spaceBelow) {
            top = rect.top + window.scrollY - popH - 6;
            const minTop = window.scrollY + offset;
            if (top < minTop) top = minTop;
        }

        if (left + popW > vpW) left = vpW - popW - 8 + window.scrollX;
        if (left < 8) left = 8;

        this.#popup.style.top = `${top}px`;
        this.#popup.style.left = `${left}px`;
        this.#popup.style.minWidth = `${Math.max(rect.width, 280)}px`;
    }

    // ─── Accessibility helpers ───────────────────────────────────────────────────

    /**
     * Trap Tab focus inside the open popup, wrapping at both ends.
     * @param {KeyboardEvent} e
     */
    #trapFocus(e) {
        const focusable = CallismartDatePicker.#$$(
            'button:not([disabled]), input:not([disabled]), select',
            this.#popup,
        ).filter((el) => el.offsetParent !== null);

        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault(); last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault(); first.focus();
        }
    }

    // ─── Theming ─────────────────────────────────────────────────────────────────

    #applyTheme() {
        const theme = this.#opts.theme === 'auto'
            ? (this.#mq?.matches ? 'dark' : 'light')
            : this.#opts.theme;
        this.#wrapper.setAttribute('data-cdp-theme', theme);
        this.#popup.setAttribute('data-cdp-theme', theme);
    }

    // ─── Event emitter ───────────────────────────────────────────────────────────

    /**
     * Dispatch to all registered listeners for an event name.
     * Errors in individual callbacks are caught and logged so one bad listener
     * cannot break the others.
     * @param {string} event
     * @param {Object} data
     */
    #emit(event, data) {
        (this.#listeners[event] || []).forEach((cb) => {
            try { cb(data); } catch (err) { console.error(err); }
        });
    }

    // ─── DOM factory helper ──────────────────────────────────────────────────────

    /**
     * Create a <button type="button"> with class, innerHTML, optional aria-label
     * and an optional click handler.
     * @param {string}        className
     * @param {string}        html
     * @param {string|null}   ariaLabel
     * @param {Function|null} onClick
     * @returns {HTMLButtonElement}
     */
    #makeButton(className, html, ariaLabel = null, onClick = null) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = className;
        btn.innerHTML = html;
        if (ariaLabel) btn.setAttribute('aria-label', ariaLabel);
        if (onClick) btn.addEventListener('click', onClick);
        return btn;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Public instance API
    // ═══════════════════════════════════════════════════════════════════════════

    /** @returns {string} ISO value string, or empty string if unset */
    getValue() { return this.#el.value; }

    /** @returns {Date|null} Clone of the selected Date, or null */
    getDate() { return this.#selectedDate ? new Date(this.#selectedDate) : null; }

    /**
     * @param {string} iso
     * @returns {this}
     */
    setValue(iso) {
        const d = CallismartDatePicker.#parseISO(iso);
        if (d) this.#setSelectedDate(d, true);
        return this;
    }

    /**
     * @param {Date} dateObj
     * @returns {this}
     */
    setDate(dateObj) {
        if (dateObj instanceof Date && !isNaN(dateObj))
            this.#setSelectedDate(new Date(dateObj), true);
        return this;
    }

    /** @returns {this} */
    clear() {
        this.#selectedDate = null;
        this.#el.value = '';
        this.#displayInput.value = '';
        this.#clearBtn.style.display = 'none';
        if (!this.#popup.hidden) this.#reRender();
        this.#el.dispatchEvent(new Event('change', { bubbles: true }));
        this.#emit('clear', {});
        return this;
    }

    open() {
        if (this.#opts.inline || this.#isOpen) return;

        const ref = this.#selectedDate || new Date();
        this.#viewYear = ref.getFullYear();
        this.#viewMonth = ref.getMonth();

        this.#renderPopupContent();
        this.#popup.hidden = false;
        this.#isOpen = true;
        this.#position();

        this.#displayInput.setAttribute('aria-expanded', 'true');
        this.#el.setAttribute('aria-expanded', 'true');

        requestAnimationFrame(() => {
            const sel = this.#popup.querySelector('.cdp-selected, .cdp-day:not(.cdp-disabled):not(.cdp-outside)');
            if (sel) sel.focus();
        });

        this.#emit('open', {});
    }

    close() {
        if (this.#opts.inline || !this.#isOpen) return;
        this.#popup.hidden = true;
        this.#isOpen = false;
        this.#displayInput.setAttribute('aria-expanded', 'false');
        this.#el.setAttribute('aria-expanded', 'false');
        this.#displayInput.focus();
        this.#emit('close', {});
    }

    toggle() { this.#isOpen ? this.close() : this.open(); }

    /** @param {string} iso  @returns {this} */
    setMin(iso) { this.#opts.min = iso; this.#reRender(); return this; }

    /** @param {string} iso  @returns {this} */
    setMax(iso) { this.#opts.max = iso; this.#reRender(); return this; }

    /** @param {number[]} arr  @returns {this} */
    setDisabledDays(arr) { this.#opts.disabledDays = arr; this.#reRender(); return this; }

    /** @param {string[]} arr  @returns {this} */
    setDisabledDates(arr) { this.#opts.disabledDates = arr; this.#reRender(); return this; }

    /** @param {string} name  'light'|'dark'|'auto'  @returns {this} */
    setTheme(name) { this.#opts.theme = name; this.#applyTheme(); return this; }

    /** @param {number} year  @param {number} month  0-based  @returns {this} */
    goToMonth(year, month) {
        this.#viewYear = year;
        this.#viewMonth = month;
        this.#reRender();
        return this;
    }

    /** @param {number} px  @returns {this} */
    setFixedHeaderOffset(px) { this.#opts.fixedHeaderOffset = px; return this; }

    /** @param {string} event  @param {Function} cb  @returns {this} */
    on(event, cb) {
        if (!this.#listeners[event]) this.#listeners[event] = [];
        this.#listeners[event].push(cb);
        return this;
    }

    /** @param {string} event  @param {Function} cb  @returns {this} */
    off(event, cb) {
        if (!this.#listeners[event]) return this;
        this.#listeners[event] = this.#listeners[event].filter((f) => f !== cb);
        return this;
    }

    /**
     * Completely tear down: remove all event listeners, delete injected DOM,
     * and restore the original input to its pre-mount state.
     */
    destroy() {
        if (!this.#opts.inline && this.#popup.parentNode)
            this.#popup.parentNode.removeChild(this.#popup);

        // Restore the id to the original input before unwrapping
        if (this.#displayInput.id) this.#el.id = this.#displayInput.id;

        this.#wrapper.parentNode.insertBefore(this.#el, this.#wrapper);
        this.#wrapper.parentNode.removeChild(this.#wrapper);

        this.#el.removeAttribute('readonly');
        this.#el.removeAttribute('aria-haspopup');
        this.#el.removeAttribute('aria-expanded');
        this.#el.removeAttribute('data-cdp-id');

        document.removeEventListener('mousedown', this.#onDocClick);
        window.removeEventListener('scroll', this.#onScroll, true);
        window.removeEventListener('resize', this.#onScroll);
        if (this.#mq && this.#onMqChange)
            this.#mq.removeEventListener('change', this.#onMqChange);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Public static API
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Mount the picker on every element matching the selector and return the
     * resulting instances. Callers are responsible for holding references.
     * @param {string} [selector]
     * @param {Object} [options]
     * @returns {CallismartDatePicker[]}
     */
    static mountAll(
        selector = 'input[type="date"], input[type="datetime-local"], input[smliser-date-picker]',
        options = {},
    ) {
        return CallismartDatePicker.#$$(selector).map((el) => new CallismartDatePicker(el, options));
    }

    /**
     * Merge options into the global defaults applied to all future instances.
     * @param {Object} options
     */
    static setGlobalDefaults(options = {}) {
        Object.assign(CallismartDatePicker.#globalDefaults, options);
    }
}