(function ($) {
  function toast($wrap, msg, ok = false) {
    const $t = $wrap.find("[data-toast]");
    $t.text(msg)
      .toggleClass("ok", ok)
      .toggleClass("bad", !ok)
      .removeAttr("hidden");
    setTimeout(() => $t.attr("hidden", true), 3500);
  }

  function ajax(action, data) {
    return $.post(APL.ajaxUrl, { action, nonce: APL.nonce, ...data });
  }

  function monthLabel(ym) {
    const [y, m] = ym.split("-").map(Number);
    const d = new Date(y, m - 1, 1);
    return d.toLocaleDateString("es-MX", { month: "long", year: "numeric" });
  }

  function buildCalendar($wrap, ym, days) {
    const [y, m] = ym.split("-").map(Number);
    const first = new Date(y, m - 1, 1);
    const startDay = (first.getDay() + 6) % 7;

    const totalDays = new Date(y, m, 0).getDate();

    let html = `<div class="apl-grid">
      <div class="apl-dow">L</div><div class="apl-dow">M</div><div class="apl-dow">X</div><div class="apl-dow">J</div><div class="apl-dow">V</div><div class="apl-dow">S</div><div class="apl-dow">D</div>`;

    for (let i = 0; i < startDay; i++)
      html += `<div class="apl-cell empty"></div>`;

    const map = new Map(days.map((d) => [d.date, d]));
    for (let day = 1; day <= totalDays; day++) {
      const dd = String(day).padStart(2, "0");
      const date = `${ym}-${dd}`;
      const info = map.get(date) || { availableCount: 0, isWorking: false };
      const has = info.availableCount > 0;
      const cls = [
        "apl-cell",
        info.isWorking ? "work" : "off",
        has ? "has" : "no",
      ].join(" ");

      html += `<button type="button" class="${cls}" data-date="${date}" ${has ? "" : "disabled"}>
        <span class="num">${day}</span>
        <span class="dot" aria-hidden="true"></span>
      </button>`;
    }

    html += `</div>`;
    $wrap.find("[data-calendar]").html(html);
    $wrap.find("[data-month-label]").text(monthLabel(ym));
  }

  function loadMonth($wrap, ym) {
    return ajax("apl_get_month", { ym }).then((res) => {
      if (!res.success)
        throw new Error(res.data?.message || "Error cargando mes");
      buildCalendar($wrap, ym, res.data.days);
      return res.data.days;
    });
  }

  function loadDaySlots($wrap, date) {
    $wrap.find("[data-slots-title]").text(`Horarios para ${date}`);
    $wrap
      .find("[data-slots-list]")
      .html(`<div class="apl-loading">Cargando horarios...</div>`);

    return ajax("apl_get_day_slots", { date }).then((res) => {
      if (!res.success)
        throw new Error(res.data?.message || "Error cargando horarios");
      const slots = res.data.slots || [];
      if (!slots.length) {
        $wrap
          .find("[data-slots-list]")
          .html(`<div class="apl-empty">No hay horarios disponibles.</div>`);
        return slots;
      }

      const html = slots
        .map((s) => {
          const disabled = s.isBlocked ? "disabled" : "";
          const cls = `apl-slot ${s.isBlocked ? "is-blocked" : ""}`;

          return `<button type="button" class="${cls}"
            ${disabled}
            data-slot-key="${s.key}"
            data-start="${s.start}"
            data-end="${s.end}">
            <span>${s.dayLabel}</span>
            <strong>${s.timeLabel}</strong>
            <em>${s.isBlocked ? "Ocupado" : "Reservar"}</em>
          </button>`;
        })
        .join("");

      $wrap.find("[data-slots-list]").html(html);
      return slots;
    });
  }

  function loadPreview($wrap) {
    const previewCount = Number($wrap.data("preview")) || 6;
    const now = new Date();
    let collected = [];

    function fmt(d) {
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, "0");
      const day = String(d.getDate()).padStart(2, "0");
      return `${y}-${m}-${day}`;
    }

    function step(i) {
      if (collected.length >= previewCount || i > 20) {
        if (!collected.length) {
          $wrap
            .find("[data-preview-list]")
            .html(`<div class="apl-empty">Sin horarios próximos.</div>`);
          return;
        }
        const html = collected
          .slice(0, previewCount)
          .map(
            (s) => `
          <div class="apl-preview-item">
            <span>${s.dayLabel}</span>
            <strong>${s.timeLabel}</strong>
          </div>
        `,
          )
          .join("");
        $wrap.find("[data-preview-list]").html(html);
        return;
      }

      const d = new Date(now);
      d.setDate(now.getDate() + i);
      const date = fmt(d);

      ajax("apl_get_day_slots", { date }).then((res) => {
        if (res.success) {
          (res.data.slots || []).forEach((s) => collected.push(s));
        }
        step(i + 1);
      });
    }

    $wrap
      .find("[data-preview-list]")
      .html(`<div class="apl-loading">Cargando...</div>`);
    step(0);
  }

  function holdAndCheckout($wrap, slot) {
    return ajax("apl_hold_and_checkout", {
      slot_key: slot.slotKey,
      start_local: slot.startLocal,
      end_local: slot.endLocal,
    }).then((res) => {
      if (!res.success)
        throw new Error(res.data?.message || "No se pudo reservar");
      window.location.href = res.data.checkoutUrl;
    });
  }

  $(document).on("click", ".apl-toggle", function () {
    const $wrap = $(this).closest(".apl-wrap");
    const expanded = $(this).attr("aria-expanded") === "true";
    $(this).attr("aria-expanded", String(!expanded));

    const $exp = $wrap.find(".apl-expanded");
    if (expanded) $exp.attr("hidden", true);
    else $exp.removeAttr("hidden");
  });

  $(document).on("click", ".apl-month-prev, .apl-month-next", function () {
    const $wrap = $(this).closest(".apl-wrap");
    const ym = $wrap.data("ym");
    const [y, m] = ym.split("-").map(Number);
    const dir = $(this).hasClass("apl-month-next") ? 1 : -1;
    const d = new Date(y, m - 1 + dir, 1);
    const newYm = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
    $wrap.data("ym", newYm);

    loadMonth($wrap, newYm).catch((e) => toast($wrap, e.message));
  });

  $(document).on("click", ".apl-cell.has", function () {
    const $wrap = $(this).closest(".apl-wrap");
    const date = $(this).data("date");
    loadDaySlots($wrap, date).catch((e) => toast($wrap, e.message));
  });

  $(document).on("click", ".apl-slot", function () {
    // guard para para navegadores modernos creo que se puede remover
    if (this.disabled) return;
    const $wrap = $(this).closest(".apl-wrap");
    const slot = {
      slotKey: $(this).data("slot-key"),
      startLocal: $(this).data("start"),
      endLocal: $(this).data("end"),
    };
    holdAndCheckout($wrap, slot).catch((e) => toast($wrap, e.message));
  });

  $(function () {
    $(".apl-wrap").each(function () {
      const $wrap = $(this);
      const ym = $wrap.data("ym");
      loadPreview($wrap);
      loadMonth($wrap, ym).catch((e) => toast($wrap, e.message));
    });
  });
})(jQuery);
