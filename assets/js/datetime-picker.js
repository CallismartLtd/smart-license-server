/**
 * CallismartDatePicker
 * A fully accessible, multi-instance datetime picker.
 * Mounts on <input type="date"> and <input type="datetime-local">
 * or any input with [smliser-date-picker] canonical attribute.
 *
 * @author Callistus Nwachukwu
 * @version 2.2.4
 * @license MIT
 */

class CallismartDatePicker {

    static #DAYS_SHORT = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

    static #DAYS_FULL = [
        'Sunday', 'Monday', 'Tuesday', 'Wednesday',
        'Thursday', 'Friday', 'Saturday',
    ];

    static #MONTHS_FULL = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    static #instanceCount = 0;
    static #globalDefaults = {};
    static #instances = new Set();
    static #themeResolver = null;
    static #themeObserver = null;

    static #pad(n) { return String(n).padStart(2, '0'); }

    static #$$(sel, ctx = document) { return [...ctx.querySelectorAll(sel)]; }

    static #parseISO(str) {
        if (!str) return null;
        const d = new Date(str.includes('T') ? str : `${str}T00:00:00`);
        return isNaN(d) ? null : d;
    }

    static #toISO(date, withTime = false) {
        if (!date) return '';
        const p = CallismartDatePicker.#pad;
        const y = date.getFullYear();
        const m = p(date.getMonth() + 1);
        const d = p(date.getDate());
        if (!withTime) return `${y}-${m}-${d}`;
        return `${y}-${m}-${d}T${p(date.getHours())}:${p(date.getMinutes())}`;
    }

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

    static #isSameDay(a, b) {
        return !!(
            a && b &&
            a.getFullYear() === b.getFullYear() &&
            a.getMonth() === b.getMonth() &&
            a.getDate() === b.getDate()
        );
    }

    static #startOfMonth(y, m) { return new Date(y, m, 1); }

    static #daysInMonth(y, m) { return new Date(y, m + 1, 0).getDate(); }

    static #snapshotInputStyles(el) {
        const cs = window.getComputedStyle(el);

        const inline = {
            fontFamily: cs.fontFamily,
            fontSize: cs.fontSize,
            fontWeight: cs.fontWeight,
            fontStyle: cs.fontStyle,
            lineHeight: cs.lineHeight,
            letterSpacing: cs.letterSpacing,
            wordSpacing: cs.wordSpacing,
            textAlign: cs.textAlign,
            textTransform: cs.textTransform,
            textDecorationLine: cs.textDecorationLine,
            textDecorationStyle: cs.textDecorationStyle,
            textDecorationColor: cs.textDecorationColor,
            textIndent: cs.textIndent,
            textShadow: cs.textShadow,
            whiteSpace: cs.whiteSpace,
            direction: cs.direction,
            verticalAlign: cs.verticalAlign,
            paddingTop: cs.paddingTop,
            paddingRight: cs.paddingRight,
            paddingBottom: cs.paddingBottom,
            paddingLeft: cs.paddingLeft,
            borderTopWidth: cs.borderTopWidth,
            borderRightWidth: cs.borderRightWidth,
            borderBottomWidth: cs.borderBottomWidth,
            borderLeftWidth: cs.borderLeftWidth,
            borderTopStyle: cs.borderTopStyle,
            borderRightStyle: cs.borderRightStyle,
            borderBottomStyle: cs.borderBottomStyle,
            borderLeftStyle: cs.borderLeftStyle,
            borderTopLeftRadius: cs.borderTopLeftRadius,
            borderTopRightRadius: cs.borderTopRightRadius,
            borderBottomLeftRadius: cs.borderBottomLeftRadius,
            borderBottomRightRadius: cs.borderBottomRightRadius,
            height: cs.height,
            minHeight: cs.minHeight,
            maxHeight: cs.maxHeight,
            width: cs.width,
            minWidth: cs.minWidth,
            maxWidth: cs.maxWidth,
            boxSizing: cs.boxSizing,
            opacity: cs.opacity,
        };

        const vars = {
            '--cdp-mirror-color': cs.color,
            '--cdp-mirror-bg': cs.backgroundColor,
            '--cdp-mirror-border-top': cs.borderTopColor,
            '--cdp-mirror-border-right': cs.borderRightColor,
            '--cdp-mirror-border-bottom': cs.borderBottomColor,
            '--cdp-mirror-border-left': cs.borderLeftColor,
        };

        return { inline, vars };
    }

    static #applySnapshotToInput(input, snapshot) {
        Object.assign(input.style, snapshot.inline);

        for (const [prop, val] of Object.entries(snapshot.vars)) {
            input.style.setProperty(prop, val);
        }

        const origPR = parseFloat(snapshot.inline.paddingRight) || 0;
        input.style.setProperty('padding-right', `${origPR + 52}px`, 'important');
    }

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

    static #resolveAutoTheme() {
        if (CallismartDatePicker.#themeResolver) {
            try {
                const r = CallismartDatePicker.#themeResolver();
                if (r === 'light' || r === 'dark') return r;
            } catch (err) {
                console.error('CallismartDatePicker: theme resolver threw', err);
            }
        }
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    static #refreshAutoInstances() {
        for (const inst of CallismartDatePicker.#instances) {
            if (inst.#opts.theme === 'auto') inst.#applyTheme();
        }
    }

    #id;
    #el;
    #opts;
    #withTime;
    #listeners = {};
    #isOpen = false;
    #selectedDate = null;
    #viewYear = null;
    #viewMonth = null;

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

    #onDisplayClick;
    #onDisplayKeydown;
    #onPopupKeydown;
    #onDocClick;
    #onScroll;
    #mq;
    #onMqChange;
    #repositionTimer = null;

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

        const snapshot = CallismartDatePicker.#snapshotInputStyles(element);

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
                fixedHeaderOffset: 0,
                boundaryEl: undefined, // undefined/null = viewport, HTMLElement = custom
            },
            CallismartDatePicker.#globalDefaults,
            this.#readAttrs(element),
            options,
        );

        this.#withTime = this.#opts.mode === 'datetime' || element.type === 'datetime-local';

        this.#init(snapshot);
        CallismartDatePicker.#instances.add(this);
    }

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

    #init(snapshot) {
        const el = this.#el;

        el.setAttribute('autocomplete', 'off');
        el.setAttribute('readonly', 'true');
        el.setAttribute('aria-haspopup', 'dialog');
        el.setAttribute('aria-expanded', 'false');
        el.setAttribute('data-cdp-id', this.#id);

        this.#wrapper = document.createElement('div');
        this.#wrapper.className = 'cdp-wrapper';
        el.parentNode.insertBefore(this.#wrapper, el);
        this.#wrapper.appendChild(el);

        this.#displayInput = document.createElement('input');
        this.#displayInput.type = 'text';

        const origClasses = (el.className || '').trim();
        this.#displayInput.className = origClasses
            ? `cdp-display-input ${origClasses}`
            : 'cdp-display-input';

        this.#displayInput.setAttribute('aria-label', el.getAttribute('aria-label') || el.getAttribute('placeholder') || 'Date picker');
        this.#displayInput.setAttribute('aria-controls', `${this.#id}-popup`);
        this.#displayInput.setAttribute('aria-expanded', 'false');
        this.#displayInput.setAttribute('aria-haspopup', 'dialog');
        this.#displayInput.setAttribute('role', 'combobox');
        this.#displayInput.setAttribute('aria-autocomplete', 'none');
        this.#displayInput.readOnly = true;
        this.#displayInput.placeholder = el.placeholder || (this.#withTime ? 'Select date & time' : 'Select date');

        if (el.id) {
            this.#displayInput.id = el.id;
            el.removeAttribute('id');
        }

        CallismartDatePicker.#applySnapshotToInput(this.#displayInput, snapshot);
        this.#wrapper.insertBefore(this.#displayInput, el);

        this.#iconBtn = this.#makeButton(
            'cdp-icon-btn',
            CallismartDatePicker.#calendarIconSVG(),
            'Open date picker',
        );
        this.#iconBtn.setAttribute('tabindex', '-1');
        this.#iconBtn.setAttribute('aria-hidden', 'true');
        this.#wrapper.appendChild(this.#iconBtn);

        this.#clearBtn = this.#makeButton('cdp-clear-btn', '&times;', 'Clear date');
        this.#clearBtn.setAttribute('tabindex', '-1');
        this.#clearBtn.style.display = 'none';
        this.#wrapper.appendChild(this.#clearBtn);

        this.#popup = document.createElement('div');
        this.#popup.className = 'cdp-popup';
        this.#popup.id = `${this.#id}-popup`;
        this.#popup.setAttribute('role', 'dialog');
        this.#popup.setAttribute('aria-modal', 'true');
        this.#popup.setAttribute('aria-label', 'Date picker dialog');

        const usePopover = !this.#opts.inline && typeof this.#popup.showPopover === 'function';
        if (usePopover) {
            this.#popup.setAttribute('popover', 'manual');
        } else {
            this.#popup.hidden = true;
        }

        if (this.#opts.inline) {
            this.#wrapper.appendChild(this.#popup);
            this.#wrapper.classList.add('cdp-inline');
        } else {
            document.body.appendChild(this.#popup);
        }

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

    #showPopup() {
        if (this.#popup.hasAttribute('popover')) {
            try { this.#popup.showPopover(); } catch (_) { /* already open */ }
        } else {
            this.#popup.hidden = false;
        }
    }

    #hidePopup() {
        if (this.#popup.hasAttribute('popover')) {
            try { this.#popup.hidePopover(); } catch (_) { /* already hidden */ }
        } else {
            this.#popup.hidden = true;
        }
    }

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

        this.#onPopupKeydown = (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                this.close();
            }
        };
        this.#popup.addEventListener('keydown', this.#onPopupKeydown);

        this.#onDocClick = (e) => {
            if (!this.#isOpen) return;

            const path = typeof e.composedPath === 'function' ? e.composedPath() : [];

            const clickedInside =
                path.includes(this.#wrapper) ||
                path.includes(this.#popup) ||
                (e.target && (
                    this.#wrapper.contains(e.target) ||
                    this.#popup.contains(e.target)
                ));

            if (!clickedInside) this.close();
        };

        document.addEventListener('pointerdown', this.#onDocClick, true);
        document.addEventListener('mousedown', this.#onDocClick, true);

        this.#onScroll = () => {
            if (this.#isOpen && !this.#opts.inline) this.#position();
        };
        window.addEventListener('scroll', this.#onScroll, true);
        window.addEventListener('resize', this.#onScroll);

        this.#mq = window.matchMedia('(prefers-color-scheme: dark)');
        this.#onMqChange = () => this.#applyTheme();
        this.#mq.addEventListener('change', this.#onMqChange);

        this.#popup.addEventListener('wheel', (e) => e.stopPropagation(), { passive: false });
        this.#popup.addEventListener('touchmove', (e) => e.stopPropagation(), { passive: false });

        this.#applyTheme();
    }

    #handleDisplayKeydown(e) {
        switch (e.key) {
            case 'Enter': case ' ': e.preventDefault(); this.toggle(); break;
            case 'Escape': this.close(); break;
            case 'Tab':
                if (this.#isOpen) this.#trapFocus(e);
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

    #renderPopupContent() {
        const ref = this.#selectedDate || new Date();
        this.#viewYear = this.#viewYear ?? ref.getFullYear();
        this.#viewMonth = this.#viewMonth ?? ref.getMonth();

        this.#popup.innerHTML = '';
        this.#popup.appendChild(this.#buildHeader());

        const body = document.createElement('div');
        body.className = 'cdp-body';
        if (this.#withTime) body.classList.add('cdp-body-datetime');

        body.appendChild(this.#buildCalendar());
        if (this.#withTime) body.appendChild(this.#buildTimePicker());

        this.#popup.appendChild(body);
        this.#popup.appendChild(this.#buildFooter());
    }

    #reRender() {
        if (!this.#isOpen && !this.#opts.inline) return;
        this.#renderPopupContent();
        // Re-run #position() because #renderPopupContent() wipes the DOM,
        // destroying the inline overflow/maxHeight styles on the .cdp-body.
        // Without this, selecting a date would remove the scrollbar and cause
        // the footer to overlap the calendar.
        if (this.#isOpen) this.#position();
    }

    #buildHeader() {
        const header = document.createElement('div');
        header.className = 'cdp-header';

        const prevBtn = this.#makeButton('cdp-nav-btn cdp-prev', '&#8249;', 'Previous month', () => this.#navigate(-1));
        const nextBtn = this.#makeButton('cdp-nav-btn cdp-next', '&#8250;', 'Next month', () => this.#navigate(1));

        const title = document.createElement('div');
        title.className = 'cdp-header-title';

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

        const prevMonthDays = DIM(this.#viewYear, this.#viewMonth - 1);
        for (let i = startOffset - 1; i >= 0; i--) {
            grid.appendChild(this.#makeOutsideCell(prevMonthDays - i));
        }

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

        const filled = startOffset + totalDays;
        const remainder = filled % 7 === 0 ? 0 : 7 - (filled % 7);
        for (let d = 1; d <= remainder; d++) grid.appendChild(this.#makeOutsideCell(d));

        container.appendChild(grid);
        this.#grid = grid;
        return container;
    }

    #makeOutsideCell(day) {
        const cell = document.createElement('div');
        cell.className = 'cdp-day cdp-outside';
        cell.setAttribute('aria-hidden', 'true');
        cell.textContent = day;
        return cell;
    }

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

    #selectDate(date) {
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
        if (this.#isOpen) this.#reRender();
    }

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

    #navigate(dir) {
        this.#viewMonth += dir;
        if (this.#viewMonth > 11) { this.#viewMonth = 0; this.#viewYear++; }
        if (this.#viewMonth < 0) { this.#viewMonth = 11; this.#viewYear--; }
        this.#reRender();
        this.#emit('navigate', { year: this.#viewYear, month: this.#viewMonth });
    }

    #getBoundaryRect() {
        const vp = {
            top: 0, left: 0,
            right: window.innerWidth, bottom: window.innerHeight,
            width: window.innerWidth, height: window.innerHeight,
        };

        const { boundaryEl } = this.#opts;

        // If null or undefined, use the viewport.
        // The picker uses the top layer (popover="manual"), so it is not
        // clipped by the modal's overflow. By default we let it use the full
        // viewport, allowing it to break out of the modal and use the
        // available space on the screen.
        if (boundaryEl == null) return vp;

        if (boundaryEl instanceof HTMLElement) {
            const r = boundaryEl.getBoundingClientRect();
            return {
                top: Math.max(r.top, 0),
                left: Math.max(r.left, 0),
                right: Math.min(r.right, vp.right),
                bottom: Math.min(r.bottom, vp.bottom),
                width: Math.min(r.right, vp.right) - Math.max(r.left, 0),
                height: Math.min(r.bottom, vp.bottom) - Math.max(r.top, 0),
            };
        }

        return vp;
    }

    #position() {
        if (this.#opts.inline) return;

        this.#popup.style.maxHeight = '';
        this.#popup.style.maxWidth = '';
        const bodyEl = this.#popup.querySelector('.cdp-body');
        if (bodyEl) bodyEl.style.overflowY = '';

        const wrapRect = this.#wrapper.getBoundingClientRect();
        const boundary = this.#getBoundaryRect();
        const GAP = 10;
        const offset = this.#opts.fixedHeaderOffset;

        const bTop = Math.max(boundary.top, offset);
        const bBottom = boundary.bottom;
        const bLeft = boundary.left;
        const bRight = boundary.right;

        let popH = this.#popup.offsetHeight;
        if (!popH) return;

        const spaceBelow = bBottom - wrapRect.bottom - GAP;
        const spaceAbove = wrapRect.top - bTop - GAP;

        const biggerSide = Math.max(spaceBelow, spaceAbove);
        if (biggerSide < popH) {
            const maxAllowed = Math.max(180, biggerSide);
            this.#popup.style.maxHeight = `${maxAllowed}px`;
            if (bodyEl) bodyEl.style.overflowY = 'auto';
            popH = this.#popup.offsetHeight;
        }

        const boundaryWidth = bRight - bLeft - 8;
        if (boundaryWidth > 0) {
            this.#popup.style.maxWidth = `${boundaryWidth}px`;
        }

        const popW = this.#popup.offsetWidth;

        let top;
        if (spaceBelow >= popH) {
            top = wrapRect.bottom + GAP;
        } else if (spaceAbove >= popH) {
            top = wrapRect.top - popH - GAP;
        } else if (spaceAbove >= spaceBelow) {
            top = wrapRect.top - popH - GAP;
        } else {
            top = wrapRect.bottom + GAP;
        }

        let left = wrapRect.left;
        const maxLeft = bRight - popW - 4;
        if (left > maxLeft) left = maxLeft;
        const minLeft = bLeft + 4;
        if (left < minLeft) left = minLeft;

        this.#popup.style.top = `${top}px`;
        this.#popup.style.left = `${left}px`;
        this.#popup.style.minWidth = `${Math.min(Math.max(wrapRect.width, 280), boundaryWidth)}px`;
    }

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

    #applyTheme() {
        const theme = this.#opts.theme === 'auto'
            ? CallismartDatePicker.#resolveAutoTheme()
            : this.#opts.theme;

        this.#wrapper.setAttribute('data-cdp-theme', theme);
        this.#popup.setAttribute('data-cdp-theme', theme);

        if (this.#el && this.#displayInput) {
            const cs = window.getComputedStyle(this.#el);
            const s = this.#displayInput.style;
            s.setProperty('--cdp-mirror-color', cs.color);
            s.setProperty('--cdp-mirror-bg', cs.backgroundColor);
            s.setProperty('--cdp-mirror-border-top', cs.borderTopColor);
            s.setProperty('--cdp-mirror-border-right', cs.borderRightColor);
            s.setProperty('--cdp-mirror-border-bottom', cs.borderBottomColor);
            s.setProperty('--cdp-mirror-border-left', cs.borderLeftColor);
        }
    }

    #emit(event, data) {
        (this.#listeners[event] || []).forEach((cb) => {
            try { cb(data); } catch (err) { console.error(err); }
        });
    }

    #makeButton(className, html, ariaLabel = null, onClick = null) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = className;
        btn.innerHTML = html;
        if (ariaLabel) btn.setAttribute('aria-label', ariaLabel);
        if (onClick) btn.addEventListener('click', onClick);
        return btn;
    }

    getValue() { return this.#el.value; }

    getDate() { return this.#selectedDate ? new Date(this.#selectedDate) : null; }

    getTheme() { return this.#wrapper.getAttribute('data-cdp-theme') || 'light'; }

    setTheme(name) {
        this.#opts.theme = name;
        this.#applyTheme();
        return this;
    }

    setValue(iso) {
        const d = CallismartDatePicker.#parseISO(iso);
        if (d) this.#setSelectedDate(d, true);
        return this;
    }

    setDate(dateObj) {
        if (dateObj instanceof Date && !isNaN(dateObj))
            this.#setSelectedDate(new Date(dateObj), true);
        return this;
    }

    clear() {
        this.#selectedDate = null;
        this.#el.value = '';
        this.#displayInput.value = '';
        this.#clearBtn.style.display = 'none';
        this.#reRender();
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
        this.#showPopup();
        this.#isOpen = true;
        this.#position();

        requestAnimationFrame(() => { if (this.#isOpen) this.#position(); });
        clearTimeout(this.#repositionTimer);
        this.#repositionTimer = setTimeout(() => {
            if (this.#isOpen) this.#position();
        }, 200);

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
        clearTimeout(this.#repositionTimer);
        this.#hidePopup();
        this.#isOpen = false;
        this.#displayInput.setAttribute('aria-expanded', 'false');
        this.#el.setAttribute('aria-expanded', 'false');
        this.#displayInput.focus();
        this.#emit('close', {});
    }

    toggle() { this.#isOpen ? this.close() : this.open(); }

    setMin(iso) { this.#opts.min = iso; this.#reRender(); return this; }

    setMax(iso) { this.#opts.max = iso; this.#reRender(); return this; }

    setDisabledDays(arr) { this.#opts.disabledDays = arr; this.#reRender(); return this; }

    setDisabledDates(arr) { this.#opts.disabledDates = arr; this.#reRender(); return this; }

    goToMonth(year, month) {
        this.#viewYear = year;
        this.#viewMonth = month;
        this.#reRender();
        return this;
    }

    setFixedHeaderOffset(px) {
        this.#opts.fixedHeaderOffset = px;
        if (this.#isOpen) this.#position();
        return this;
    }

    setBoundaryEl(el) {
        this.#opts.boundaryEl = el;
        if (this.#isOpen) this.#position();
        return this;
    }

    on(event, cb) {
        if (!this.#listeners[event]) this.#listeners[event] = [];
        this.#listeners[event].push(cb);
        return this;
    }

    off(event, cb) {
        if (!this.#listeners[event]) return this;
        this.#listeners[event] = this.#listeners[event].filter((f) => f !== cb);
        return this;
    }

    destroy() {
        CallismartDatePicker.#instances.delete(this);
        clearTimeout(this.#repositionTimer);

        if (this.#popup.hasAttribute('popover')) {
            try { this.#popup.hidePopover(); } catch (_) { /* not open */ }
        }

        if (!this.#opts.inline && this.#popup.parentNode)
            this.#popup.parentNode.removeChild(this.#popup);

        if (this.#displayInput.id) this.#el.id = this.#displayInput.id;

        this.#wrapper.parentNode.insertBefore(this.#el, this.#wrapper);
        this.#wrapper.parentNode.removeChild(this.#wrapper);

        this.#el.removeAttribute('readonly');
        this.#el.removeAttribute('aria-haspopup');
        this.#el.removeAttribute('aria-expanded');
        this.#el.removeAttribute('data-cdp-id');

        document.removeEventListener('pointerdown', this.#onDocClick, true);
        document.removeEventListener('mousedown', this.#onDocClick, true);
        window.removeEventListener('scroll', this.#onScroll, true);
        window.removeEventListener('resize', this.#onScroll);
        if (this.#mq && this.#onMqChange)
            this.#mq.removeEventListener('change', this.#onMqChange);
    }

    static mountAll(
        selector = 'input[type="date"], input[type="datetime-local"], input[smliser-date-picker]',
        options = {},
    ) {
        return CallismartDatePicker.#$$(selector).map((el) => new CallismartDatePicker(el, options));
    }

    static setGlobalDefaults(options = {}) {
        Object.assign(CallismartDatePicker.#globalDefaults, options);
    }

    static setGlobalTheme(theme) {
        for (const inst of CallismartDatePicker.#instances) {
            inst.setTheme(theme);
        }
    }

    static observeThemeAttribute({
        target = document.documentElement,
        attribute = 'data-theme',
        dark = 'dark',
    } = {}) {
        if (CallismartDatePicker.#themeObserver) {
            CallismartDatePicker.#themeObserver.disconnect();
            CallismartDatePicker.#themeObserver = null;
        }

        CallismartDatePicker.#themeResolver = () =>
            target.getAttribute(attribute) === dark ? 'dark' : 'light';

        CallismartDatePicker.#themeObserver = new MutationObserver(() => {
            CallismartDatePicker.#refreshAutoInstances();
        });

        CallismartDatePicker.#themeObserver.observe(target, {
            attributes: true,
            attributeFilter: [attribute],
        });

        CallismartDatePicker.#refreshAutoInstances();
    }

    static unobserveThemeAttribute() {
        if (CallismartDatePicker.#themeObserver) {
            CallismartDatePicker.#themeObserver.disconnect();
            CallismartDatePicker.#themeObserver = null;
        }
        CallismartDatePicker.#themeResolver = null;
        CallismartDatePicker.#refreshAutoInstances();
    }

    static setThemeResolver(fn) {
        CallismartDatePicker.#themeResolver = typeof fn === 'function' ? fn : null;
        CallismartDatePicker.#refreshAutoInstances();
    }
}