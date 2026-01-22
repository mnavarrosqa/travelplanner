(() => {
  'use strict';
  
  /**
   * Reservation PDF Auto-fill (Booking.com + Airbnb)
   * - Extracts text client-side using PDF.js (pdfjsLib global)
   * - Parses provider-specific patterns
   * - Prefills hotel fields + dates + confirmation
   */
  
  const STATE = {
    add: { provider: null, prompted: false, dismissed: false, parsing: false, detectedProvider: null },
    edit: { provider: null, prompted: false, dismissed: false, parsing: false, detectedProvider: null },
  };
  
  function isPdfFile(file) {
    if (!file) return false;
    const name = (file.name || '').toLowerCase();
    const type = (file.type || '').toLowerCase();
    return type === 'application/pdf' || name.endsWith('.pdf');
  }
  
  function pad2(n) {
    return String(n).padStart(2, '0');
  }
  
  function formatDateTimeLocal(dateObj, timeHHMM) {
    const yyyy = dateObj.getFullYear();
    const mm = pad2(dateObj.getMonth() + 1);
    const dd = pad2(dateObj.getDate());
    return `${yyyy}-${mm}-${dd}T${timeHHMM}`;
  }
  
  function normalizeWhitespace(s) {
    return String(s || '').replace(/\r/g, '').replace(/[ \t]+/g, ' ').trim();
  }
  
  function firstMatch(text, re, groupIndex = 1) {
    const m = re.exec(text);
    return m && m[groupIndex] ? String(m[groupIndex]).trim() : null;
  }
  
  function findMaxYear(text) {
    const years = [];
    const re = /\b(20\d{2})\b/g;
    let m;
    while ((m = re.exec(text)) !== null) years.push(Number(m[1]));
    if (!years.length) return new Date().getFullYear();
    return Math.max(...years);
  }
  
  function bumpYearIfTooOld(dateObj, today = new Date()) {
    // If the parsed date is far in the past (likely because the PDF omitted the year),
    // bump it forward until it's within a reasonable window.
    const cutoff = new Date(today);
    cutoff.setDate(cutoff.getDate() - 330);
    const d = new Date(dateObj);
    while (d < cutoff) {
      d.setFullYear(d.getFullYear() + 1);
    }
    return d;
  }
  
  function parseSpanishMonthNameToNumber(monthNameUpper) {
    const m = String(monthNameUpper || '')
      .trim()
      .toUpperCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
    const map = {
      ENERO: 1,
      FEBRERO: 2,
      MARZO: 3,
      ABRIL: 4,
      MAYO: 5,
      JUNIO: 6,
      JULIO: 7,
      AGOSTO: 8,
      SEPTIEMBRE: 9,
      SETIEMBRE: 9,
      OCTUBRE: 10,
      NOVIEMBRE: 11,
      DICIEMBRE: 12,
    };
    return map[m] || null;
  }
  
  function parseSpanishMonthAbbrToNumber(monthAbbr) {
    const m = String(monthAbbr || '')
      .trim()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
    const map = {
      ene: 1,
      feb: 2,
      mar: 3,
      abr: 4,
      may: 5,
      jun: 6,
      jul: 7,
      ago: 8,
      sep: 9,
      sept: 9,
      oct: 10,
      nov: 11,
      dic: 12,
    };
    return map[m] || null;
  }
  
  function parseEnglishMonthNameToNumber(monthName) {
    const m = String(monthName || '')
      .trim()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
    const map = {
      january: 1,
      february: 2,
      march: 3,
      april: 4,
      may: 5,
      june: 6,
      july: 7,
      august: 8,
      september: 9,
      october: 10,
      november: 11,
      december: 12,
      jan: 1,
      feb: 2,
      mar: 3,
      apr: 4,
      jun: 6,
      jul: 7,
      aug: 8,
      sep: 9,
      sept: 9,
      oct: 10,
      nov: 11,
      dec: 12,
    };
    return map[m] || null;
  }

  function parseItalianMonthNameToNumber(monthName) {
    const m = String(monthName || '')
      .trim()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
    const map = {
      gennaio: 1,
      febbraio: 2,
      marzo: 3,
      aprile: 4,
      maggio: 5,
      giugno: 6,
      luglio: 7,
      agosto: 8,
      settembre: 9,
      ottobre: 10,
      novembre: 11,
      dicembre: 12,
      gen: 1,
      feb: 2,
      mar: 3,
      apr: 4,
      mag: 5,
      giu: 6,
      lug: 7,
      ago: 8,
      set: 9,
      ott: 10,
      nov: 11,
      dic: 12,
    };
    return map[m] || null;
  }

  function parseMonthTokenToNumber(token) {
    const t = String(token || '').replace(/\./g, '').trim();
    return (
      parseSpanishMonthNameToNumber(t) ||
      parseSpanishMonthAbbrToNumber(t) ||
      parseItalianMonthNameToNumber(t) ||
      parseEnglishMonthNameToNumber(t)
    );
  }

  function buildDateFromDayMonth(day, monthNum, baseYear, today = new Date()) {
    if (!day || !monthNum) return null;
    const d0 = new Date(baseYear, monthNum - 1, day);
    return bumpYearIfTooOld(d0, today);
  }
  
  function extractLikelyTitleFromTop(lines, stopWordsRe) {
    const titleParts = [];
    for (const line of lines) {
      const l = line.trim();
      if (!l) continue;
      if (stopWordsRe.test(l)) break;
      titleParts.push(l);
      if (titleParts.join(' ').length > 140) break;
    }
    return normalizeWhitespace(titleParts.join(' ')) || null;
  }
  
  function findBookingHotelName(lines) {
    const blacklist = /^(Confirmaci[oó]n\s+de\s+la\s+reserva|N[ÚU]MERO\s+DE\s+CONFIRMACI[ÓO]N|C[ÓO]DIGO\s+PIN|ENTRADA\b|SALIDA\b|UNIDADES\b|NOCHES\b|Direcci[oó]n\b|Tel[eé]fono\b|TU\s+GRUPO\b|PRECIO\b|Coordenadas\s+GPS\b)/i;
    for (const line of lines) {
      const l = String(line || '').trim();
      if (!l) continue;
      if (blacklist.test(l)) continue;
      // Avoid lines that are just numbers or short tokens.
      if (!/[A-Za-zÁÉÍÓÚÑáéíóúñ]/.test(l)) continue;
      if (l.length < 6) continue;
      return l;
    }
    return lines[0] || null;
  }

  function parseBookingAddressFromLines(lines) {
    const idx = lines.findIndex((l) => /^Direcci[oó]n\s*:?/i.test(l));
    if (idx === -1) return null;
    const first = String(lines[idx] || '').replace(/^Direcci[oó]n\s*:?\s*/i, '').trim();
    const parts = [first].filter(Boolean);
    // Often the country is on the next line (e.g., "España")
    for (let j = idx + 1; j < Math.min(idx + 4, lines.length); j += 1) {
      const nxt = String(lines[j] || '').trim();
      if (!nxt) continue;
      if (/^(Tel[eé]fono|TU\s+GRUPO|PRECIO|Coordenadas\s+GPS|ENTRADA\b|SALIDA\b)/i.test(nxt)) break;
      if (/^\d{1,2}\s+\d{1,2}\b/.test(nxt)) break;
      if (/^(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SEPTIEMBRE|SETIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE)\b/i.test(nxt)) break;
      parts.push(nxt);
      // Usually we only need 1 extra line
      if (parts.join(' ').length > 180) break;
    }
    return normalizeWhitespace(parts.join(' ')) || null;
  }

  function parseBookingText(text) {
    const raw = String(text || '');
    const lines = raw.split('\n').map((l) => l.trim()).filter(Boolean);
    const today = new Date();
    const baseYear = findMaxYear(raw);
    
    const hotelName =
      extractLikelyTitleFromTop(lines, /^(Direcci[oó]n:|TU GRUPO|PRECIO|Confirmaci[oó]n|ENTRADA|SALIDA)\b/i) ||
      findBookingHotelName(lines);
    
    const address =
      parseBookingAddressFromLines(lines) ||
      firstMatch(raw, /Direcci[oó]n\s*:?\s*([^\n]+)/i) ||
      firstMatch(raw, /Direcci[oó]n\s*\n\s*([^\n]+)/i);
    const phone = firstMatch(raw, /Tel[eé]fono:\s*([+\d][\d\s().-]{6,})/i);
    
    // Guests: prefer TU GRUPO section, fallback to "Número de personas"
    let guests = firstMatch(raw, /TU\s+GRUPO[\s\S]*?(\d+)\s+adultos?\b/i);
    if (!guests) guests = firstMatch(raw, /N[uú]mero\s+de\s+personas:\s*(\d+)/i);
    
    let units = firstMatch(raw, /^UNIDADES\s+(\d+)/im);
    if (!units) units = firstMatch(raw, /\bUNIDADES\s+(\d+)\b/i);
    
    // Confirmation number formats vary a lot in extracted text:
    // - "NÚMERO DE CONFIRMACIÓN: 5072.304.781"
    // - "NÚMERO CONFIRMACION 5072304781"
    // - punctuation/spacing/newlines may be inconsistent
    const confirmation =
      firstMatch(raw, /N[ÚU]MERO\s+(?:DE\s+)?CONFIRMACI[ÓO]N\s*[:：]?\s*([0-9][0-9.\- ]{6,})/i) ||
      firstMatch(raw, /\b(\d{3,6}(?:\.\d{2,6}){1,4})\b/, 1) ||
      firstMatch(raw, /\b(\d{9,12})\b/, 1);
    const pin = firstMatch(raw, /C[ÓO]DIGO\s+PIN:\s*(\d+)/i);
    
    // Room type: pick the first line that looks like a property unit type
    const roomTypeLine =
      firstMatch(raw, /^(Apartamento|Habitaci[oó]n|Estudio|Casa|Villa|D[uú]plex)\b[^\n]*/im, 0) ||
      firstMatch(raw, /\b(Apartamento|Habitaci[oó]n|Estudio|Casa|Villa|D[uú]plex)\b[^\n]*/i, 0);
    
    // Check-in (ENTRADA) and check-out (SALIDA)
    // PDF.js extraction may return these as multi-line blocks OR a single joined line.
    const inMatch =
      /ENTRADA[\s\S]*?\n\s*(\d{1,2})\s*\n\s*([A-Za-zÁÉÍÓÚÑáéíóúñ]+)[\s\S]*?de\s*(\d{1,2}:\d{2})\s*a\s*(\d{1,2}:\d{2})/i.exec(raw) ||
      /ENTRADA\s+(\d{1,2})\s+([A-Za-zÁÉÍÓÚÑáéíóúñ]+)[\s\S]*?de\s*(\d{1,2}:\d{2})\s*a\s*(\d{1,2}:\d{2})/i.exec(raw);
    const outMatch =
      /SALIDA[\s\S]*?\n\s*(\d{1,2})\s*\n\s*([A-Za-zÁÉÍÓÚÑáéíóúñ]+)[\s\S]*?de\s*(\d{1,2}:\d{2})\s*a\s*(\d{1,2}:\d{2})/i.exec(raw) ||
      /SALIDA\s+(\d{1,2})\s+([A-Za-zÁÉÍÓÚÑáéíóúñ]+)[\s\S]*?de\s*(\d{1,2}:\d{2})\s*a\s*(\d{1,2}:\d{2})/i.exec(raw);
    
    let startDateTime = null;
    let endDateTime = null;
    let checkInTime = null;
    let checkOutTime = null;
    
    if (inMatch) {
      const day = Number(inMatch[1]);
      const monthNum = parseSpanishMonthNameToNumber(inMatch[2]);
      const dateObj = buildDateFromDayMonth(day, monthNum, baseYear, today);
      const timeStart = inMatch[3];
      checkInTime = timeStart;
      if (dateObj && timeStart) startDateTime = formatDateTimeLocal(dateObj, timeStart);
    }
    
    if (outMatch) {
      const day = Number(outMatch[1]);
      const monthNum = parseSpanishMonthNameToNumber(outMatch[2]);
      // Use startDate year as baseline if available, otherwise baseYear
      const baselineYear = startDateTime ? Number(startDateTime.slice(0, 4)) : baseYear;
      let dateObj = buildDateFromDayMonth(day, monthNum, baselineYear, today);
      // If checkout is before checkin, bump a year
      if (dateObj && startDateTime) {
        const checkInDateOnly = new Date(Number(startDateTime.slice(0, 4)), Number(startDateTime.slice(5, 7)) - 1, Number(startDateTime.slice(8, 10)));
        if (dateObj < checkInDateOnly) dateObj.setFullYear(dateObj.getFullYear() + 1);
      }
      const timeEnd = outMatch[4]; // prefer end of window, e.g. "11:00"
      checkOutTime = timeEnd;
      if (dateObj && timeEnd) endDateTime = formatDateTimeLocal(dateObj, timeEnd);
    }

    // Booking.com "table layout" fallback:
    // ENTRADA SALIDA UNIDADES NOCHES
    // 31 8 1 / 8
    // ENERO FEBRERO
    // de 15:00 a 00:00 de 00:00 a 11:00
    if (!startDateTime || !endDateTime) {
      const dayLine = firstMatch(raw, /^\s*(\d{1,2})\s+(\d{1,2})\s+(\d+)\s*\/\s*(\d+)\s*$/m, 0);
      const dayLineMatch = dayLine ? /^\s*(\d{1,2})\s+(\d{1,2})\s+(\d+)\s*\/\s*(\d+)\s*$/.exec(dayLine) : null;
      const monthLineMatch = /^(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SEPTIEMBRE|SETIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE)\s+(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SEPTIEMBRE|SETIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE)\s*$/im.exec(raw);
      const timeLineMatch = /de\s*(\d{1,2}:\d{2})\s*a\s*(\d{1,2}:\d{2})\s+de\s*(\d{1,2}:\d{2})\s*a\s*(\d{1,2}:\d{2})/i.exec(raw);

      if (dayLineMatch && monthLineMatch && timeLineMatch) {
        const inDay = Number(dayLineMatch[1]);
        const outDay = Number(dayLineMatch[2]);
        const inferredUnits = Number(dayLineMatch[3]);
        if (!units && Number.isFinite(inferredUnits)) units = String(inferredUnits);

        const inMonthNum = parseSpanishMonthNameToNumber(monthLineMatch[1]);
        const outMonthNum = parseSpanishMonthNameToNumber(monthLineMatch[2]);

        const checkIn = timeLineMatch[1];
        const checkOut = timeLineMatch[4]; // end of second window

        const inDateObj = buildDateFromDayMonth(inDay, inMonthNum, baseYear, today);
        if (inDateObj && checkIn && !startDateTime) {
          startDateTime = formatDateTimeLocal(inDateObj, checkIn);
          checkInTime = checkInTime || checkIn;
        }

        const baselineYear = startDateTime ? Number(startDateTime.slice(0, 4)) : baseYear;
        let outDateObj = buildDateFromDayMonth(outDay, outMonthNum, baselineYear, today);
        if (outDateObj && startDateTime) {
          const checkInDateOnly = new Date(Number(startDateTime.slice(0, 4)), Number(startDateTime.slice(5, 7)) - 1, Number(startDateTime.slice(8, 10)));
          if (outDateObj < checkInDateOnly) outDateObj.setFullYear(outDateObj.getFullYear() + 1);
        }
        if (outDateObj && checkOut && !endDateTime) {
          endDateTime = formatDateTimeLocal(outDateObj, checkOut);
          checkOutTime = checkOutTime || checkOut;
        }
      }
    }
    
    return {
      provider: 'booking',
      confirmation_number: confirmation || null,
      start_datetime: startDateTime,
      end_datetime: endDateTime,
      notes_append: pin ? `Booking PIN: ${pin}` : null,
      hotel: {
        hotel_name: hotelName || null,
        address: address || null,
        phone: phone || null,
        number_of_guests: guests ? Number(guests) : null,
        number_of_rooms: units ? Number(units) : null,
        room_type_raw: roomTypeLine ? normalizeWhitespace(roomTypeLine) : null,
        check_in_time: checkInTime || null,
        check_out_time: checkOutTime || null,
      },
    };
  }
  
  function parseAirbnbText(text) {
    const raw = String(text || '');
    const lines = raw.split('\n').map((l) => l.trim());
    const nonEmpty = lines.filter(Boolean);
    const today = new Date();
    const baseYear = findMaxYear(raw);
    
    const title = extractLikelyTitleFromTop(nonEmpty, /^(Llegada|Salida|Qui[eé]n\s+viene\?|C[oó]digo\s+de\s+confirmaci[oó]n|Direcci[oó]n)\b/i);
    const confirmation = firstMatch(raw, /C[oó]digo\s+de\s+confirmaci[oó]n\s*\n?\s*([A-Z0-9]{6,})/i);
    const guests = firstMatch(raw, /(\d+)\s+viajeros?\b/i);
    const address =
      firstMatch(raw, /Direcci[oó]n\s*\n\s*([^\n]+)/i) ||
      firstMatch(raw, /Direcci[oó]n\s*:?\s*([^\n]+)/i);
    const host = firstMatch(raw, /Anfitri[oó]n:\s*([^\n]+)/i);
    
    const inMatch =
      /Llegada\s*\n\s*(\d{1,2}:\d{2})\s*\n\s*[a-záéíóúñ]{3},\s*(\d{1,2})\s+([a-záéíóúñ]{3})\./i.exec(raw) ||
      /Llegada\s+(\d{1,2}:\d{2})\s+[a-záéíóúñ]{3},\s*(\d{1,2})\s+([a-záéíóúñ]{3})\./i.exec(raw);
    const outMatch =
      /Salida\s*\n\s*(\d{1,2}:\d{2})\s*\n\s*[a-záéíóúñ]{3},\s*(\d{1,2})\s+([a-záéíóúñ]{3})\./i.exec(raw) ||
      /Salida\s+(\d{1,2}:\d{2})\s+[a-záéíóúñ]{3},\s*(\d{1,2})\s+([a-záéíóúñ]{3})\./i.exec(raw);
    
    let startDateTime = null;
    let endDateTime = null;
    
    if (inMatch) {
      const time = inMatch[1];
      const day = Number(inMatch[2]);
      const monthNum = parseSpanishMonthAbbrToNumber(inMatch[3]);
      const dateObj = buildDateFromDayMonth(day, monthNum, baseYear, today);
      if (dateObj && time) startDateTime = formatDateTimeLocal(dateObj, time);
    }
    
    if (outMatch) {
      const time = outMatch[1];
      const day = Number(outMatch[2]);
      const monthNum = parseSpanishMonthAbbrToNumber(outMatch[3]);
      const baselineYear = startDateTime ? Number(startDateTime.slice(0, 4)) : baseYear;
      let dateObj = buildDateFromDayMonth(day, monthNum, baselineYear, today);
      if (dateObj && startDateTime) {
        const checkInDateOnly = new Date(Number(startDateTime.slice(0, 4)), Number(startDateTime.slice(5, 7)) - 1, Number(startDateTime.slice(8, 10)));
        if (dateObj < checkInDateOnly) dateObj.setFullYear(dateObj.getFullYear() + 1);
      }
      if (dateObj && time) endDateTime = formatDateTimeLocal(dateObj, time);
    }
    
    return {
      provider: 'airbnb',
      confirmation_number: confirmation || null,
      start_datetime: startDateTime,
      end_datetime: endDateTime,
      notes_append: host ? `Airbnb host: ${host}` : null,
      hotel: {
        hotel_name: title || null,
        address: address || null,
        phone: null,
        number_of_guests: guests ? Number(guests) : null,
        number_of_rooms: null,
        room_type_raw: null,
        check_in_time: startDateTime ? startDateTime.slice(11, 16) : null,
        check_out_time: endDateTime ? endDateTime.slice(11, 16) : null,
      },
    };
  }

  function showParseDebugDialog({ providerLabel, parsed, pdfText }) {
    if (typeof window.customModal?.show !== 'function') return;
    const safeText = String(pdfText || '').slice(0, 4000);
    const parsedSummary = normalizeWhitespace(
      JSON.stringify(
        {
          confirmation_number: parsed?.confirmation_number || null,
          start_datetime: parsed?.start_datetime || null,
          end_datetime: parsed?.end_datetime || null,
          hotel_name: parsed?.hotel?.hotel_name || null,
          address: parsed?.hotel?.address || null,
          phone: parsed?.hotel?.phone || null,
          guests: parsed?.hotel?.number_of_guests || null,
          rooms: parsed?.hotel?.number_of_rooms || null,
        },
        null,
        2
      )
    );

    const body = `
      <div style="display:flex; flex-direction:column; gap:0.75rem;">
        <p style="margin:0;">
          We couldn’t confidently parse this PDF as <strong>${providerLabel}</strong>.
        </p>
        <div style="display:flex; flex-direction:column; gap:0.5rem;">
          <div style="font-weight:600; font-size:0.9rem;">Parsed fields (preview)</div>
          <textarea readonly style="width:100%; min-height:120px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 12px; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 8px;">${parsedSummary}</textarea>
        </div>
        <div style="display:flex; flex-direction:column; gap:0.5rem;">
          <div style="font-weight:600; font-size:0.9rem;">Extracted text (first part)</div>
          <textarea readonly style="width:100%; min-height:160px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 12px; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 8px;">${safeText}</textarea>
          <div style="color: var(--text-light); font-size:0.85rem;">
            Tip: you can copy this text and send it to tune the parser.
          </div>
        </div>
      </div>
    `;
    const footer = `
      <button type="button" class="btn btn-secondary" id="pdfDebugClose">Close</button>
    `;
    window.customModal.show('PDF import details', body, 'dialog', { html: true, footer });
    document.getElementById('pdfDebugClose')?.addEventListener('click', () => window.customModal.close(false));
  }
  
  function parseGenericText(text) {
    const raw = String(text || '');
    const lines = raw.split('\n').map((l) => l.trim()).filter(Boolean);
    const today = new Date();
    const baseYear = findMaxYear(raw);

    // Title: first non-empty line that isn't a generic heading.
    const title = (() => {
      const blacklist = /^(confirmaci[oó]n|confirmation|conferma|reservation|prenotazione|booking|itinerary|invoice|recibo|factura)\b/i;
      for (const l of lines) {
        if (!l) continue;
        if (blacklist.test(l)) continue;
        if (l.length < 6) continue;
        return l;
      }
      return lines[0] || null;
    })();

    // Confirmation / reference code
    const confirmationRaw =
      // Booking-style (Spanish/Italian/English)
      firstMatch(raw, /\b(?:n[úu]mero|numero)\s+(?:di\s+)?(?:conferma|confirmaci[oó]n|confirmation)\s*[:：]?\s*([0-9][0-9.\- ]{6,})/i) ||
      firstMatch(raw, /\b(?:n[úu]mero)\s+de\s+confirmaci[oó]n\s*[:：]?\s*([0-9][0-9.\- ]{6,})/i) ||
      firstMatch(raw, /\b(?:confirmation|confirmaci[oó]n|conferma|reservation|prenotazione|reserva|booking)\b[\s\S]{0,60}?\b(?:number|n[úu]mero|numero|code|c[oó]digo|codice|reference|ref(?:erencia)?)\b\s*[:：]?\s*([A-Z0-9.\-]{6,})/i) ||
      firstMatch(raw, /\b(?:reference|ref)\b\s*[:：]?\s*([A-Z0-9.\-]{6,})/i) ||
      null;
    const confirmation = (() => {
      const v = (confirmationRaw || '').trim();
      // Avoid false positives like "CONFERMA"
      if (!v) return null;
      if (!/[0-9]/.test(v) && v.length < 8) return null;
      return normalizeWhitespace(v);
    })();

    // Address: label + next content
    const address =
      firstMatch(raw, /\b(?:address|direcci[oó]n|adresse|indirizzo|indirizzo|alamat)\b\s*[:：]?\s*([^\n]+)/i) ||
      firstMatch(raw, /\b(?:address|direcci[oó]n|adresse|indirizzo|alamat)\b\s*\n\s*([^\n]+)/i);

    // Phone
    const phone =
      firstMatch(raw, /\b(?:phone|tel[eé]fono|telephone|t[eé]l[eé]phone|telefon)\b\s*[:：]?\s*([+\d][\d\s().-]{6,})/i) ||
      firstMatch(raw, /\b([+]\d[\d\s().-]{6,})\b/, 1);

    // Guests
    const guests =
      firstMatch(raw, /\b(\d+)\s+(?:guests?|travelers?|travellers?|viajeros?|viaggiatori|adultos?|adulti|ospiti)\b/i) ||
      firstMatch(raw, /\b(?:guests?|viajeros?|viaggiatori|adultos?|adulti|ospiti)\b\s*[:：]?\s*(\d+)\b/i) ||
      firstMatch(raw, /\bnumero\s+di\s+ospiti\s*[:：]?\s*(\d+)\b/i);

    // Check-in/out dates & times - try labeled patterns first
    const checkInLabel = /(check[- ]?in|arrival|arrivo|entrada|llegada)\b/i;
    const checkOutLabel = /(check[- ]?out|departure|partenza|salida)\b/i;

    const textFlat = normalizeWhitespace(raw.replace(/\n+/g, ' \n '));

    function parseDateTimeFromSnippet(snippet) {
      // Accept: yyyy-mm-dd, dd/mm/yyyy, dd-mm-yyyy, "31 ENERO", "23 mar.", "23 March"
      // Return { dateObj, timeHHMM|null }
      const time = firstMatch(snippet, /\b(\d{1,2}:\d{2})\b/, 1);

      // ISO
      let m = /\b(20\d{2})-(\d{2})-(\d{2})\b/.exec(snippet);
      if (m) {
        return { dateObj: new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])), time };
      }

      // d/m(/y)
      m = /\b(\d{1,2})[\/.-](\d{1,2})(?:[\/.-](\d{2,4}))?\b/.exec(snippet);
      if (m) {
        const day = Number(m[1]);
        const month = Number(m[2]);
        let year = m[3] ? Number(m[3]) : baseYear;
        if (year < 100) year += 2000;
        return { dateObj: bumpYearIfTooOld(new Date(year, month - 1, day), today), time };
      }

      // Spanish month name
      m = /\b(\d{1,2})\s+([A-Za-zÁÉÍÓÚÑáéíóúñ.]{3,})\b/.exec(snippet);
      if (m) {
        const day = Number(m[1]);
        const monthToken = String(m[2]).replace(/\./g, '');
        const monthNum = parseMonthTokenToNumber(monthToken);
        if (monthNum) {
          return { dateObj: buildDateFromDayMonth(day, monthNum, baseYear, today), time };
        }
      }

      return { dateObj: null, time };
    }

    function findLabeledDate(labelRe) {
      // Capture up to ~120 chars after the label across whitespace/newlines.
      const re = new RegExp(`${labelRe.source}[\\s\\S]{0,120}`, 'i');
      const m = re.exec(textFlat);
      if (!m) return null;
      const snippet = m[0];
      return parseDateTimeFromSnippet(snippet);
    }

    const inDt = findLabeledDate(checkInLabel);
    const outDt = findLabeledDate(checkOutLabel);

    let startDateTime = null;
    let endDateTime = null;
    let checkInTime = null;
    let checkOutTime = null;

    if (inDt?.dateObj) {
      checkInTime = inDt.time || null;
      startDateTime = formatDateTimeLocal(inDt.dateObj, inDt.time || '15:00');
    }
    if (outDt?.dateObj) {
      checkOutTime = outDt.time || null;
      // If checkout is before checkin, bump year
      let d = outDt.dateObj;
      if (d && startDateTime) {
        const inDateOnly = new Date(Number(startDateTime.slice(0, 4)), Number(startDateTime.slice(5, 7)) - 1, Number(startDateTime.slice(8, 10)));
        if (d < inDateOnly) d = new Date(d.getFullYear() + 1, d.getMonth(), d.getDate());
      }
      endDateTime = formatDateTimeLocal(d, outDt.time || '11:00');
    }

    // Generic booking-style "table layout" fallback (ARRIVO/PARTENZA/CAMERE/NOTTI, etc.)
    if (!startDateTime || !endDateTime) {
      const dayLineMatch = /^\s*(\d{1,2})\s+(\d{1,2})\s+(\d+)\s*\/\s*(\d+)\s*$/m.exec(raw);
      const monthLineMatch =
        /^(GENNAIO|FEBBRAIO|MARZO|APRILE|MAGGIO|GIUGNO|LUGLIO|AGOSTO|SETTEMBRE|OTTOBRE|NOVEMBRE|DICEMBRE)\s+(GENNAIO|FEBBRAIO|MARZO|APRILE|MAGGIO|GIUGNO|LUGLIO|AGOSTO|SETTEMBRE|OTTOBRE|NOVEMBRE|DICEMBRE)\s*$/im.exec(raw) ||
        /^(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SEPTIEMBRE|SETIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE)\s+(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SEPTIEMBRE|SETIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE)\s*$/im.exec(raw) ||
        /^(JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)\s+(JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)\s*$/im.exec(raw);

      const timeLineMatch =
        /de\s*(\d{1,2}:\d{2})\s*a\s*(\d{1,2}:\d{2})\s+de\s*(\d{1,2}:\d{2})\s*a\s*(\d{1,2}:\d{2})/i.exec(raw) ||
        /(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})\s+(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/i.exec(raw);

      if (dayLineMatch && monthLineMatch && timeLineMatch) {
        const inDay = Number(dayLineMatch[1]);
        const outDay = Number(dayLineMatch[2]);
        const roomsMaybe = Number(dayLineMatch[3]);
        const inMonth = parseMonthTokenToNumber(monthLineMatch[1]);
        const outMonth = parseMonthTokenToNumber(monthLineMatch[2]);

        const inTime = timeLineMatch[1];
        const outTime = timeLineMatch[4];

        if (Number.isFinite(roomsMaybe) && !Number.isFinite(Number(guests))) {
          // rooms isn't stored by generic parser; leave as notes or ignore
        }

        if (inMonth && inDay && !startDateTime) {
          const inDateObj = buildDateFromDayMonth(inDay, inMonth, baseYear, today);
          if (inDateObj) startDateTime = formatDateTimeLocal(inDateObj, inTime || '15:00');
          checkInTime = checkInTime || inTime || null;
        }

        if (outMonth && outDay && !endDateTime) {
          const baselineYear = startDateTime ? Number(startDateTime.slice(0, 4)) : baseYear;
          let outDateObj = buildDateFromDayMonth(outDay, outMonth, baselineYear, today);
          if (outDateObj && startDateTime) {
            const inDateOnly = new Date(Number(startDateTime.slice(0, 4)), Number(startDateTime.slice(5, 7)) - 1, Number(startDateTime.slice(8, 10)));
            if (outDateObj < inDateOnly) outDateObj.setFullYear(outDateObj.getFullYear() + 1);
          }
          if (outDateObj) endDateTime = formatDateTimeLocal(outDateObj, outTime || '11:00');
          checkOutTime = checkOutTime || outTime || null;
        }
      }
    }

    return {
      provider: 'other',
      confirmation_number: confirmation || null,
      start_datetime: startDateTime,
      end_datetime: endDateTime,
      notes_append: null,
      hotel: {
        hotel_name: title || null,
        address: address || null,
        phone: phone || null,
        number_of_guests: guests ? Number(guests) : null,
        number_of_rooms: null,
        room_type_raw: null,
        check_in_time: checkInTime || null,
        check_out_time: checkOutTime || null,
      },
    };
  }

  function hasValue(v) {
    if (v === null || v === undefined) return false;
    if (typeof v === 'string') return v.trim().length > 0;
    if (typeof v === 'number') return Number.isFinite(v);
    return true;
  }
  
  function isRobustEnough(parsed) {
    if (!parsed) return false;
    const hasConfirmation = hasValue(parsed.confirmation_number);
    const hasStart = hasValue(parsed.start_datetime);
    const hasEnd = hasValue(parsed.end_datetime);
    const hasNameOrAddress = hasValue(parsed.hotel?.hotel_name) || hasValue(parsed.hotel?.address);
    return hasConfirmation && hasStart && hasEnd && hasNameOrAddress;
  }
  
  function pickRoomTypeOption(rawRoomType) {
    const t = normalizeWhitespace(rawRoomType).toLowerCase();
    if (!t) return null;
    // The UI uses a fixed set: standard/deluxe/suite/executive/presidential/other.
    // Booking/Airbnb strings rarely map cleanly, so default to "other".
    if (/\bsuite\b/.test(t)) return 'suite';
    return 'other';
  }
  
  function setFieldValue(el, value, { overwrite = false } = {}) {
    if (!el || !hasValue(value)) return;
    const next = String(value);
    if (!overwrite && el.value && el.value.trim() !== '') return;
    el.value = next;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }
  
  function appendToTextarea(el, line) {
    if (!el || !hasValue(line)) return;
    const existing = (el.value || '').trim();
    const addition = String(line).trim();
    if (!addition) return;
    if (!existing) {
      el.value = addition;
    } else if (!existing.includes(addition)) {
      el.value = `${existing}\n${addition}`;
    }
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }
  
  function getContextEls(context) {
    if (context === 'edit') {
      return {
        typeSelect: document.getElementById('edit_type'),
        fileInput: document.getElementById('edit_item_files'),
        title: document.getElementById('edit_title'),
        location: document.getElementById('edit_location'),
        start: document.getElementById('edit_start_datetime'),
        end: document.getElementById('edit_end_datetime'),
        confirmation: document.getElementById('edit_confirmation_number'),
        notes: document.getElementById('edit_notes'),
        hotelName: document.getElementById('edit_hotel_name'),
        hotelAddress: document.getElementById('edit_hotel_address'),
        hotelPhone: document.getElementById('edit_hotel_phone'),
        hotelGuests: document.getElementById('edit_hotel_number_of_guests'),
        hotelRooms: document.getElementById('edit_hotel_number_of_rooms'),
        hotelRoomType: document.getElementById('edit_hotel_room_type'),
        hotelCheckIn: document.getElementById('edit_hotel_check_in_time'),
        hotelCheckOut: document.getElementById('edit_hotel_check_out_time'),
        hotelSpecial: document.getElementById('edit_hotel_special_requests'),
      };
    }
    
    return {
      typeSelect: document.getElementById('type'),
      fileInput: document.getElementById('item_files'),
      title: document.getElementById('item_title'),
      location: document.getElementById('location'),
      start: document.getElementById('start_datetime'),
      end: document.getElementById('end_datetime'),
      confirmation: document.getElementById('confirmation_number'),
      notes: document.getElementById('notes'),
      hotelName: document.getElementById('hotel_name'),
      hotelAddress: document.getElementById('hotel_address'),
      hotelPhone: document.getElementById('hotel_phone'),
      hotelGuests: document.getElementById('hotel_number_of_guests'),
      hotelRooms: document.getElementById('hotel_number_of_rooms'),
      hotelRoomType: document.getElementById('hotel_room_type'),
      hotelCheckIn: document.getElementById('hotel_check_in_time'),
      hotelCheckOut: document.getElementById('hotel_check_out_time'),
      hotelSpecial: document.getElementById('hotel_special_requests'),
    };
  }
  
  async function showProviderDialog(contextLabel) {
    if (typeof window.customModal?.show !== 'function') return null;
    
    const body = `
      <div style="display:flex; flex-direction:column; gap:0.75rem;">
        <p style="margin:0;">Is your reservation made with Booking.com or Airbnb?</p>
        <p style="margin:0; color: var(--text-light); font-size: 0.9rem;">
          We can try to read a reservation PDF and auto-fill hotel fields for this ${contextLabel}.
        </p>
      </div>
    `;
    const footer = `
      <button type="button" class="btn btn-secondary" id="resProvSkip">Skip</button>
      <button type="button" class="btn" id="resProvAuto">Auto-detect</button>
      <button type="button" class="btn" id="resProvBooking">Booking.com</button>
      <button type="button" class="btn" id="resProvAirbnb">Airbnb</button>
      <button type="button" class="btn" id="resProvOther">Other</button>
    `;
    
    const p = window.customModal.show('Reservation source', body, 'dialog', { html: true, footer });
    
    const wire = (id, value) => {
      const btn = document.getElementById(id);
      if (!btn) return;
      btn.addEventListener('click', () => {
        if (typeof window.customModal?.resolve === 'function') {
          window.customModal.resolve(value);
        }
        window.customModal.close(false);
      });
    };
    
    wire('resProvSkip', null);
    wire('resProvAuto', 'auto');
    wire('resProvBooking', 'booking');
    wire('resProvAirbnb', 'airbnb');
    wire('resProvOther', 'other');
    
    return p;
  }

  // Provider picker that runs an action synchronously from the click handler.
  // This is important because browsers may block file pickers unless opened
  // directly from a user gesture.
  function showProviderPicker(contextLabel, onPick) {
    if (typeof window.customModal?.show !== 'function') return;
    const body = `
      <div style="display:flex; flex-direction:column; gap:0.75rem;">
        <p style="margin:0;">Is your reservation made with Booking.com or Airbnb?</p>
        <p style="margin:0; color: var(--text-light); font-size: 0.9rem;">
          After you pick a provider, we’ll open a file chooser to select the reservation PDF for this ${contextLabel}.
        </p>
      </div>
    `;
    const footer = `
      <button type="button" class="btn btn-secondary" id="resProvCancel">Cancel</button>
      <button type="button" class="btn" id="resProvAutoAction">Auto-detect</button>
      <button type="button" class="btn" id="resProvBookingAction">Booking.com</button>
      <button type="button" class="btn" id="resProvAirbnbAction">Airbnb</button>
      <button type="button" class="btn" id="resProvOtherAction">Other</button>
    `;
    window.customModal.show('Reservation source', body, 'dialog', { html: true, footer });

    const wire = (id, value) => {
      const btn = document.getElementById(id);
      if (!btn) return;
      btn.addEventListener('click', () => {
        try {
          if (value) onPick?.(value);
        } finally {
          window.customModal.close(false);
        }
      });
    };

    wire('resProvCancel', null);
    wire('resProvAutoAction', 'auto');
    wire('resProvBookingAction', 'booking');
    wire('resProvAirbnbAction', 'airbnb');
    wire('resProvOtherAction', 'other');
  }
  
  async function showPdfPrompt(providerName) {
    if (typeof window.customModal?.show !== 'function') return false;
    
    const body = `
      <div style="display:flex; flex-direction:column; gap:0.75rem;">
        <p style="margin:0;">Do you have a reservation PDF from ${providerName}?</p>
        <p style="margin:0; color: var(--text-light); font-size: 0.9rem;">
          Choose the PDF and we’ll try to extract confirmation, dates, address, and guest count.
        </p>
      </div>
    `;
    const footer = `
      <button type="button" class="btn btn-secondary" id="resPdfSkip">Not now</button>
      <button type="button" class="btn" id="resPdfChoose">Choose PDF</button>
    `;
    
    const p = window.customModal.show('Import reservation', body, 'dialog', { html: true, footer });
    
    const wire = (id, value) => {
      const btn = document.getElementById(id);
      if (!btn) return;
      btn.addEventListener('click', () => {
        if (typeof window.customModal?.resolve === 'function') {
          window.customModal.resolve(value);
        }
        window.customModal.close(false);
      });
    };
    
    wire('resPdfSkip', false);
    wire('resPdfChoose', true);
    
    return p;
  }
  
  function ensurePdfJsReady() {
    const ok = typeof window.pdfjsLib !== 'undefined' && typeof window.pdfjsLib.getDocument === 'function';
    return ok;
  }
  
  async function extractTextFromPdfFile(file) {
    if (!ensurePdfJsReady()) {
      throw new Error('PDF.js is not available');
    }
    const buf = await file.arrayBuffer();
    // CSP note:
    // Some deployments block `blob:` workers (and don't set `worker-src`), which breaks PDF.js default worker loading.
    // Running without a worker avoids CSP violations at the cost of doing parsing on the main thread.
    const pdf = await window.pdfjsLib.getDocument({ data: buf, disableWorker: true }).promise;
    
    const allLines = [];
    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum += 1) {
      const page = await pdf.getPage(pageNum);
      const textContent = await page.getTextContent({ normalizeWhitespace: true });
      const items = Array.isArray(textContent.items) ? textContent.items : [];
      
      // Group by Y coordinate (rough line reconstruction)
      const lineMap = new Map(); // yBucket -> [{x, str}]
      for (const it of items) {
        const str = (it?.str || '').trim();
        if (!str) continue;
        const x = Array.isArray(it.transform) ? it.transform[4] : 0;
        const y = Array.isArray(it.transform) ? it.transform[5] : 0;
        const yBucket = Math.round(y * 2) / 2; // 0.5pt buckets
        if (!lineMap.has(yBucket)) lineMap.set(yBucket, []);
        lineMap.get(yBucket).push({ x, str });
      }
      
      const yBuckets = Array.from(lineMap.keys()).sort((a, b) => b - a);
      const pageLines = [];
      for (const yb of yBuckets) {
        const parts = lineMap.get(yb).sort((a, b) => a.x - b.x).map((p) => p.str);
        pageLines.push(normalizeWhitespace(parts.join(' ')));
      }
      
      if (pageLines.length) {
        allLines.push(...pageLines, ''); // blank line between pages
      }
    }
    
    return allLines.join('\n').trim();
  }
  
  async function maybeAutofillFromPdf({ context, provider, file }) {
    const state = STATE[context];
    const els = getContextEls(context);
    
    if (!file || !isPdfFile(file)) return;
    if (!provider) return;
    if (state.parsing) return;
    state.parsing = true;
    
    const originalDisabled = !!els.fileInput?.disabled;
    if (els.fileInput) els.fileInput.disabled = true;
    document.body.style.cursor = 'progress';
    
    try {
      const pdfText = await extractTextFromPdfFile(file);
      if (!pdfText || pdfText.length < 40) {
        await window.customAlert?.('Could not read text from this PDF. It may be scanned or protected. Please fill the hotel fields manually.', 'PDF import');
        return;
      }
      
      let parsed = null;
      let detectedProvider = null;

      if (provider === 'auto') {
        const candidates = [
          { p: 'booking', parsed: parseBookingText(pdfText) },
          { p: 'airbnb', parsed: parseAirbnbText(pdfText) },
          { p: 'other', parsed: parseGenericText(pdfText) },
        ];
        const robust = candidates.find((c) => isRobustEnough(c.parsed));
        if (robust) {
          parsed = robust.parsed;
          detectedProvider = robust.p;
        } else {
          candidates.sort((a, b) => scoreParsed(b.parsed) - scoreParsed(a.parsed));
          parsed = candidates[0]?.parsed || null;
          detectedProvider = candidates[0]?.p || null;
        }
      } else {
        parsed =
          provider === 'booking'
            ? parseBookingText(pdfText)
            : provider === 'airbnb'
              ? parseAirbnbText(pdfText)
              : parseGenericText(pdfText);
      }
      
      if (!isRobustEnough(parsed)) {
        const providerLabelText =
          provider === 'auto'
            ? `Auto-detect${detectedProvider ? ` (best guess: ${providerLabel(detectedProvider)})` : ''}`
            : provider === 'booking'
              ? 'Booking.com'
              : provider === 'airbnb'
                ? 'Airbnb'
                : 'Other';
        // Expose last extraction for debugging in devtools (optional).
        window.__lastReservationPdfText = pdfText;
        await window.customAlert?.(
          `We couldn’t confidently parse this PDF as ${providerLabelText}. Click OK to see details you can copy for troubleshooting.`,
          'PDF import'
        );
        showParseDebugDialog({ providerLabel: providerLabelText, parsed, pdfText });
        return;
      }
      
      if (provider === 'auto' && detectedProvider) {
        setProviderForContext(context, 'auto', detectedProvider);
      }

      const providerLabelText =
        provider === 'auto'
          ? `Auto-detect (${providerLabel(detectedProvider || 'other')})`
          : provider === 'booking'
            ? 'Booking.com'
            : provider === 'airbnb'
              ? 'Airbnb'
              : 'Other';
      const confirmed = await window.customConfirm?.(
        `Reservation details found from ${providerLabelText}. Apply them to the form?`,
        'Apply reservation details',
        { confirmText: 'Apply' }
      );
      
      if (!confirmed) return;
      
      // Prefill core fields
      setFieldValue(els.title, parsed.hotel.hotel_name, { overwrite: false });
      setFieldValue(els.confirmation, parsed.confirmation_number, { overwrite: false });
      setFieldValue(els.start, parsed.start_datetime, { overwrite: false });
      setFieldValue(els.end, parsed.end_datetime, { overwrite: false });
      
      // Hotel fields
      setFieldValue(els.hotelName, parsed.hotel.hotel_name, { overwrite: false });
      setFieldValue(els.hotelAddress, parsed.hotel.address, { overwrite: false });
      setFieldValue(els.hotelPhone, parsed.hotel.phone, { overwrite: false });
      if (Number.isFinite(parsed.hotel.number_of_guests)) {
        setFieldValue(els.hotelGuests, String(parsed.hotel.number_of_guests), { overwrite: false });
      }
      if (Number.isFinite(parsed.hotel.number_of_rooms)) {
        setFieldValue(els.hotelRooms, String(parsed.hotel.number_of_rooms), { overwrite: false });
      }
      
      // Check-in/out times (hotel_data)
      setFieldValue(els.hotelCheckIn, parsed.hotel.check_in_time, { overwrite: true });
      setFieldValue(els.hotelCheckOut, parsed.hotel.check_out_time, { overwrite: true });
      
      // Room type select (best-effort)
      if (els.hotelRoomType && hasValue(parsed.hotel.room_type_raw)) {
        const opt = pickRoomTypeOption(parsed.hotel.room_type_raw);
        if (opt) {
          setFieldValue(els.hotelRoomType, opt, { overwrite: false });
        }
        // Preserve the original provider room type in notes for clarity
        appendToTextarea(els.notes, `Room type (from PDF): ${parsed.hotel.room_type_raw}`);
      }
      
      // Append provider-specific notes
      if (parsed.notes_append) appendToTextarea(els.notes, parsed.notes_append);
    } finally {
      document.body.style.cursor = '';
      if (els.fileInput) els.fileInput.disabled = originalDisabled;
      state.parsing = false;
    }
  }
  
  function getHotelGroupEl(context) {
    return document.getElementById(context === 'edit' ? 'edit_hotel_fields_group' : 'hotel_fields_group');
  }
  
  function providerLabel(provider) {
    if (provider === 'auto') return 'Auto-detect';
    if (provider === 'booking') return 'Booking.com';
    if (provider === 'airbnb') return 'Airbnb';
    if (provider === 'other') return 'Other';
    return 'Not set';
  }

  function setProviderForContext(context, provider, detectedProvider = null) {
    STATE[context].provider = provider;
    STATE[context].detectedProvider = detectedProvider;
    const host = document.querySelector(`[data-reservation-controls="${context}"]`);
    const labelEl = host?.querySelector('[data-provider-label]');
    if (!labelEl) return;
    if (provider === 'auto' && detectedProvider) {
      labelEl.textContent = `Auto (${providerLabel(detectedProvider)})`;
    } else {
      labelEl.textContent = providerLabel(provider);
    }
  }

  function scoreParsed(parsed) {
    if (!parsed) return 0;
    let score = 0;
    if (hasValue(parsed.confirmation_number)) score += 3;
    if (hasValue(parsed.start_datetime)) score += 2;
    if (hasValue(parsed.end_datetime)) score += 2;
    if (hasValue(parsed.hotel?.hotel_name)) score += 1;
    if (hasValue(parsed.hotel?.address)) score += 1;
    if (hasValue(parsed.hotel?.phone)) score += 0.5;
    if (Number.isFinite(parsed.hotel?.number_of_guests)) score += 0.5;
    return score;
  }
  
  function ensureControls(context) {
    const host = getHotelGroupEl(context);
    if (!host) return;
    if (host.querySelector(`[data-reservation-controls="${context}"]`)) return;
    
    const wrap = document.createElement('div');
    wrap.setAttribute('data-reservation-controls', context);
    wrap.style.cssText =
      'margin-bottom: 1rem; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: 10px; background: rgba(74, 144, 226, 0.05);';
    
    wrap.innerHTML = `
      <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:1rem;">
        <div style="min-width:0;">
          <div style="font-weight:600; color: var(--text-color); display:flex; align-items:center; gap:0.5rem;">
            <i class="fas fa-file-pdf" style="color: var(--primary-color);"></i>
            Import reservation PDF
          </div>
          <div style="margin-top:0.25rem; color: var(--text-light); font-size:0.9rem;">
            Provider: <span data-provider-label>Not set</span>
          </div>
        </div>
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap; justify-content:flex-end;">
          <button type="button" class="btn btn-secondary btn-small" data-change-provider>Change provider</button>
          <button type="button" class="btn btn-small" data-import-pdf>Import PDF</button>
        </div>
      </div>
    `;
    
    // Insert at the very top of the hotel group, inside its card container
    host.prepend(wrap);
    
    const labelEl = wrap.querySelector('[data-provider-label]');
    const changeBtn = wrap.querySelector('[data-change-provider]');
    const importBtn = wrap.querySelector('[data-import-pdf]');
    
    const updateLabel = () => {
      const state = STATE[context];
      if (!labelEl) return;
      if (state.provider === 'auto' && state.detectedProvider) {
        labelEl.textContent = `Auto (${providerLabel(state.detectedProvider)})`;
      } else {
        labelEl.textContent = providerLabel(state.provider);
      }
    };
    
    updateLabel();
    
    if (changeBtn) {
      changeBtn.addEventListener('click', async () => {
        const provider = await showProviderDialog(context === 'edit' ? 'edit' : 'new item');
        if (!provider) return;
        setProviderForContext(context, provider, null);
        updateLabel();
      });
    }
    
    if (importBtn) {
      importBtn.addEventListener('click', async () => {
        const els = getContextEls(context);
        if (!els.fileInput) {
          await window.customAlert?.('File upload input was not found on this page.', 'PDF import');
          return;
        }

        // If provider is already chosen, open file chooser immediately (user gesture).
        if (STATE[context].provider) {
          els.fileInput.focus();
          els.fileInput.click();
          return;
        }

        // Otherwise, pick provider then open file chooser from the provider button click.
        showProviderPicker(context === 'edit' ? 'edit' : 'new item', (provider) => {
          setProviderForContext(context, provider, null);
          updateLabel();
          els.fileInput.focus();
          els.fileInput.click();
        });
      });
    }
  }
  
  async function onFileInputChange(context, fileInput) {
    const state = STATE[context];
    const files = fileInput ? Array.from(fileInput.files || []) : [];
    const pdf = files.find(isPdfFile);
    if (!pdf) return;
    
    if (!state.provider) {
      const provider = await showProviderDialog(context === 'edit' ? 'edit' : 'new item');
      if (!provider) return;
      setProviderForContext(context, provider, null);
    }
    
    if (!state.provider) return;
    await maybeAutofillFromPdf({ context, provider: state.provider, file: pdf });
  }
  
  function initPdfWorkerFromCdn() {
    try {
      if (window.pdfjsLib?.GlobalWorkerOptions && window.__PDFJS_WORKER_SRC__) {
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = window.__PDFJS_WORKER_SRC__;
      }
    } catch (_) {
      // ignore
    }
  }
  
  function initContext(context) {
    const els = getContextEls(context);
    if (!els.typeSelect) return;
    
    // Ensure the import controls exist (they live inside the hotel section)
    ensureControls(context);
    
    // When switching to hotel, make sure controls are present and up to date.
    els.typeSelect.addEventListener('change', () => {
      ensureControls(context);
    });
    queueMicrotask(() => ensureControls(context));
    
    // Parse PDF when selected
    if (els.fileInput) {
      els.fileInput.addEventListener('change', () => onFileInputChange(context, els.fileInput));
    }
  }
  
  function boot() {
    initPdfWorkerFromCdn();
    initContext('add');
    initContext('edit');
  }
  
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();

