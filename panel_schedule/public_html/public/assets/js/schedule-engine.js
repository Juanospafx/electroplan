class ScheduleEngine {
  constructor(polesConfig, data) {
    this.polesConfig = polesConfig;
    const totalPoles = this.normalizePoles(polesConfig);
    const rows = Math.ceil(totalPoles / 2);
    this.boundaries = this.computeBoundaries(polesConfig);
    this.left = this.buildSlots('L', rows, data?.left);
    this.right = this.buildSlots('R', rows, data?.right);
  }

  normalizePoles(polesConfig) {
    const match = String(polesConfig).match(/\d+/);
    return match ? parseInt(match[0], 10) : 42;
  }

  buildSlots(side, rows, existing) {
    const slots = [];
    for (let i = 0; i < rows; i += 1) {
      const item = existing?.[i];
      if (item) {
        slots.push({
          id: item.id || `${side}-${i + 1}`,
          span_head_id: item.span_head_id ?? null,
          span_length: item.span_length ?? 1,
          disabled: !!item.disabled,
          breaker_span: item.breaker_span ?? '1',
          description: item.description ?? '',
          load_value: item.load_value ?? item.load_va ?? '',
          load_unit: item.load_unit ?? 'VA',
          load_category: item.load_category ?? 'lighting',
          notes: item.notes ?? '',
        });
      } else {
        slots.push(this.emptySlot(side, i));
      }
    }
    return slots;
  }

  emptySlot(side, index) {
    return {
      id: `${side}-${index + 1}`,
      span_head_id: null,
      span_length: 1,
      disabled: false,
      breaker_span: '1',
      description: '',
      load_value: '',
      load_unit: 'VA',
      load_category: 'lighting',
      notes: '',
    };
  }

  computeBoundaries(polesConfig) {
    const map = window.scheduleConfig?.boundariesByPoles || {};
    return new Set(map[polesConfig] || []);
  }

  normalizeSpan(spanValue) {
    if (spanValue === '1ST') return 2;
    if (spanValue === '2ST') return 3;
    if (spanValue === '3ST') return 4;
    const num = parseInt(spanValue, 10);
    return Number.isNaN(num) ? 1 : num;
  }

  validateSpanDoesNotCrossBoundary(rowIndex, span, totalSlots) {
    const end = rowIndex + span - 1;
    if (end >= totalSlots) {
      return false;
    }
    for (const boundary of this.boundaries) {
      if (rowIndex <= boundary && end > boundary) {
        return false;
      }
    }
    return true;
  }

  applySpan(side, rowIndex, spanValue) {
    const span = this.normalizeSpan(spanValue);
    const slots = side === 'L' ? this.left : this.right;
    const slot = slots[rowIndex];
    if (!slot) return;

    if (!this.validateSpanDoesNotCrossBoundary(rowIndex, span, slots.length)) {
      return;
    }

    if (slot.span_head_id) {
      const headIndex = slots.findIndex((s) => s.id === slot.span_head_id);
      if (headIndex >= 0) {
        this.unspan(side, headIndex);
      }
    }

    this.unspan(side, rowIndex);

    if (span <= 1) {
      slot.span_length = 1;
      slot.breaker_span = String(spanValue);
      slot.disabled = false;
      slot.span_head_id = null;
      return;
    }

    slot.span_length = span;
    slot.breaker_span = String(spanValue);
    slot.disabled = false;
    slot.span_head_id = null;

    for (let i = 1; i < span; i += 1) {
      const child = slots[rowIndex + i];
      if (!child) continue;
      child.disabled = true;
      child.span_head_id = slot.id;
      child.span_length = 1;
      child.breaker_span = '';
      child.description = '';
      child.load_value = '';
      child.load_unit = 'VA';
      child.load_category = 'lighting';
      child.notes = '';
    }
  }

  unspan(side, headRowIndex) {
    const slots = side === 'L' ? this.left : this.right;
    const head = slots[headRowIndex];
    if (!head) return;
    const length = head.span_length || 1;
    if (length <= 1) {
      head.span_head_id = null;
      head.disabled = false;
      return;
    }
    for (let i = 1; i < length; i += 1) {
      const child = slots[headRowIndex + i];
      if (!child) continue;
      Object.assign(child, this.emptySlot(side, headRowIndex + i));
    }
    head.span_length = 1;
    head.span_head_id = null;
    head.disabled = false;
  }

  toJSON() {
    return {
      poles_config: this.polesConfig,
      left: this.left,
      right: this.right,
    };
  }
}

window.ScheduleEngine = ScheduleEngine;
