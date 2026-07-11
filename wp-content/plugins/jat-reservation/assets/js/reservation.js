(() => {
    'use strict';

    const config = window.JATReservationConfig || {};
    const root = document.querySelector('.jat-reservation');
    if (!root) return;

    const form = root.querySelector('#jat-reservation-form');
    const steps = Array.from(form.querySelectorAll('[data-step]'));
    const progress = Array.from(root.querySelectorAll('[data-progress-step]'));
    const previousButton = form.querySelector('[data-action="previous"]');
    const nextButton = form.querySelector('[data-action="next"]');
    const submitButton = form.querySelector('[data-action="submit"]');
    const status = form.querySelector('.jat-reservation__status');
    const errorSummary = root.querySelector('.jat-reservation__error-summary');
    const successPanel = root.querySelector('.jat-reservation__success');
    const storageKey = config.storageKey || 'jat_reservation_draft_v1';
    let currentStep = 1;
    let nonce = config.nonce || '';
    let idempotencyKey = createUuid();
    let submitting = false;
    let saveTimer = 0;

    const labels = {
        service_type: 'サービス', location_id: '空港・駅', terminal_area: 'ターミナル・改札・会合区域',
        service_date: 'ご利用日', scheduled_time: '到着・出発予定時刻', flight_number: '便名・便番号',
        train_number: '列車名・列車番号', origin_destination: '出発地・到着地', quote_only: '概算・対応可否の相談',
        lead_passenger_name: '代表者名', group_name: '団体名', passenger_count_adult: '大人人数',
        passenger_count_child: '子ども人数', luggage_count: '手荷物の個数', checked_baggage: '受託手荷物',
        emergency_phone: '当日の緊急連絡先', mobility_support: '移動・行動に関する配慮',
        signboard_text: 'サインボード表示名', signboard_company: '会社名・団体名の表示',
        service_language: 'ご希望の対応言語', special_notes: 'ご希望・配慮事項', customer_type: 'お申込者区分',
        company_name: '会社名・団体名', department: '部署名', applicant_name: 'ご担当者名',
        applicant_email: 'メールアドレス', applicant_phone: '電話番号', contract_customer: '既存の法人契約',
        contract_code: '契約コード', destination: 'ご案内先・目的地', transport_type: '会合後の交通手段',
        driver_name: '乗務員名', driver_phone: '乗務員連絡先', vehicle_info: '車種・車番・配車場所'
    };

    const optionLabels = {
        airport_meet: '空港お迎え', airport_send: '空港お見送り', station_meet: '駅お迎え', station_send: '駅お見送り',
        transfer_support: '乗継サポート（要相談）', attend: 'アテンドサービス（要相談）', haneda: '羽田空港',
        narita: '成田空港', tokyo: '東京駅', shinagawa: '品川駅', other: 'その他（要相談）', yes: 'あり', no: 'なし',
        undecided: '未定・要相談', japanese: '日本語', consultation: 'その他の言語（要相談）', individual: '個人',
        corporate: '法人・団体', unknown: '不明', arranged_vehicle: '当社または依頼元が手配した車両',
        customer_vehicle: 'お客様側の手配車両', public_transport: '公共交通機関', wheelchair: '車いすを利用',
        walking: '長距離歩行に配慮が必要', infant: '乳幼児を同伴', support_other: 'その他の配慮を相談'
    };

    function createUuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
        const bytes = new Uint8Array(16);
        window.crypto.getRandomValues(bytes);
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    }

    function setGroup(group, visible, clearWhenHidden = true) {
        group.hidden = !visible;
        group.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = !visible;
            if (!visible && clearWhenHidden) {
                if (field.type === 'checkbox' || field.type === 'radio') field.checked = false;
                else field.value = '';
                clearFieldError(field);
            }
        });
    }

    function updateConditionals() {
        const service = form.elements.service_type.value;
        const isAirport = service === 'airport_meet' || service === 'airport_send';
        const isStation = service === 'station_meet' || service === 'station_send';
        const isMeet = service === 'airport_meet' || service === 'station_meet';
        const isConsultation = service === 'transfer_support' || service === 'attend';
        root.querySelectorAll('[data-service-group="airport"]').forEach((group) => setGroup(group, isAirport));
        root.querySelectorAll('[data-service-group="station"]').forEach((group) => setGroup(group, isStation));
        root.querySelectorAll('[data-service-group="meet"]').forEach((group) => setGroup(group, isMeet));
        root.querySelectorAll('[data-consultation-note]').forEach((note) => { note.hidden = !isConsultation; });

        const corporate = form.elements.customer_type.value === 'corporate';
        root.querySelectorAll('[data-customer-group="corporate"]').forEach((group) => setGroup(group, corporate));

        const contracted = corporate && form.elements.contract_customer.value === 'yes';
        root.querySelectorAll('[data-contract-group="yes"]').forEach((group) => setGroup(group, contracted));

        const customerVehicle = isMeet && form.elements.transport_type.value === 'customer_vehicle';
        root.querySelectorAll('[data-vehicle-group="customer_vehicle"]').forEach((group) => setGroup(group, customerVehicle));
        updateSignPreview();
    }

    function updateSignPreview() {
        const name = form.elements.signboard_text && form.elements.signboard_text.value.trim();
        const company = form.elements.signboard_company && form.elements.signboard_company.value.trim();
        const nameOutput = root.querySelector('[data-sign-preview]');
        const companyOutput = root.querySelector('[data-sign-company-preview]');
        if (nameOutput) nameOutput.textContent = name || 'お客様名';
        if (companyOutput) companyOutput.textContent = company || '';
    }

    function showStep(step) {
        currentStep = Math.max(1, Math.min(5, step));
        steps.forEach((fieldset) => { fieldset.hidden = Number(fieldset.dataset.step) !== currentStep; });
        progress.forEach((item) => {
            const number = Number(item.dataset.progressStep);
            item.classList.toggle('is-current', number === currentStep);
            item.classList.toggle('is-complete', number < currentStep);
            if (number === currentStep) item.setAttribute('aria-current', 'step');
            else item.removeAttribute('aria-current');
        });
        previousButton.hidden = currentStep === 1;
        nextButton.hidden = currentStep === 5;
        submitButton.hidden = currentStep !== 5;
        hideErrorSummary();
        if (currentStep === 5) buildSummary();
        steps[currentStep - 1].querySelector('legend').focus?.();
        root.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    }

    function validationMessage(field) {
        if (field.validity.valueMissing) return `${fieldLabel(field)}を入力または選択してください。`;
        if (field.validity.typeMismatch) return `${fieldLabel(field)}の形式をご確認ください。`;
        if (field.validity.rangeUnderflow || field.validity.rangeOverflow) return `${fieldLabel(field)}の範囲をご確認ください。`;
        if (field.validity.tooLong) return `${fieldLabel(field)}が長すぎます。`;
        return `${fieldLabel(field)}をご確認ください。`;
    }

    function fieldLabel(field) {
        const key = field.name.replace('[]', '');
        return labels[key] || '入力内容';
    }

    function setFieldError(field, message) {
        field.setAttribute('aria-invalid', 'true');
        const error = document.getElementById(`${field.id}-error`);
        if (error) error.textContent = message;
    }

    function clearFieldError(field) {
        field.removeAttribute('aria-invalid');
        const error = field.id ? document.getElementById(`${field.id}-error`) : null;
        if (error) error.textContent = '';
    }

    function validateStep(stepNumber) {
        const step = steps[stepNumber - 1];
        const invalid = [];
        step.querySelectorAll('input:not(:disabled), select:not(:disabled), textarea:not(:disabled)').forEach((field) => {
            clearFieldError(field);
            if (!field.checkValidity()) {
                const message = validationMessage(field);
                setFieldError(field, message);
                invalid.push({ field, message });
            }
        });
        if (invalid.length) {
            showErrorSummary(invalid);
            invalid[0].field.focus();
            return false;
        }
        hideErrorSummary();
        return true;
    }

    function showErrorSummary(errors) {
        const list = errorSummary.querySelector('ul');
        list.replaceChildren();
        errors.forEach(({ field, message }) => {
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.href = field.id ? `#${field.id}` : '#jat-reservation-form';
            link.textContent = message;
            item.appendChild(link);
            list.appendChild(item);
        });
        errorSummary.hidden = false;
        errorSummary.focus();
    }

    function hideErrorSummary() {
        errorSummary.hidden = true;
        errorSummary.querySelector('ul').replaceChildren();
    }

    function formObject() {
        const data = {};
        const formData = new FormData(form);
        formData.forEach((value, key) => {
            const normalizedKey = key.replace('[]', '');
            if (key.endsWith('[]')) {
                if (!Array.isArray(data[normalizedKey])) data[normalizedKey] = [];
                data[normalizedKey].push(value);
            } else {
                data[normalizedKey] = value;
            }
        });
        ['quote_only', 'privacy_consent', 'terms_consent', 'marketing_consent', 'final_confirm'].forEach((key) => {
            data[key] = form.elements[key] ? Boolean(form.elements[key].checked) : false;
        });
        return data;
    }

    function displayValue(key, value) {
        if (Array.isArray(value)) return value.map((item) => optionLabels[item] || item).join('、');
        if (typeof value === 'boolean') return value ? 'はい' : 'いいえ';
        return optionLabels[value] || String(value);
    }

    function buildSummary() {
        const summary = root.querySelector('[data-summary]');
        summary.replaceChildren();
        const data = formObject();
        const sections = [
            ['サービス・ご利用日時', ['service_type', 'location_id', 'terminal_area', 'service_date', 'scheduled_time', 'flight_number', 'train_number', 'origin_destination', 'quote_only']],
            ['ご利用者情報', ['lead_passenger_name', 'group_name', 'passenger_count_adult', 'passenger_count_child', 'luggage_count', 'checked_baggage', 'emergency_phone', 'mobility_support']],
            ['お出迎え・お見送り内容', ['signboard_text', 'signboard_company', 'service_language', 'special_notes']],
            ['お申込者・車両情報', ['customer_type', 'company_name', 'department', 'applicant_name', 'applicant_email', 'applicant_phone', 'contract_customer', 'contract_code', 'destination', 'transport_type', 'driver_name', 'driver_phone', 'vehicle_info']]
        ];
        sections.forEach(([title, keys], index) => {
            const section = document.createElement('section');
            const heading = document.createElement('h3');
            heading.textContent = title;
            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'jat-summary-edit';
            edit.textContent = '修正する';
            edit.addEventListener('click', () => showStep(index + 1));
            heading.appendChild(edit);
            section.appendChild(heading);
            const list = document.createElement('dl');
            keys.forEach((key) => {
                const value = data[key];
                if (value === undefined || value === '' || value === false || (Array.isArray(value) && value.length === 0)) return;
                const term = document.createElement('dt');
                const description = document.createElement('dd');
                term.textContent = labels[key] || key;
                description.textContent = displayValue(key, value);
                list.append(term, description);
            });
            section.appendChild(list);
            summary.appendChild(section);
        });
    }

    function saveDraft() {
        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(() => {
            try {
                const data = formObject();
                ['privacy_consent', 'terms_consent', 'marketing_consent', 'final_confirm', 'website'].forEach((key) => delete data[key]);
                localStorage.setItem(storageKey, JSON.stringify({ data, currentStep, idempotencyKey, savedAt: Date.now() }));
                status.textContent = '入力内容をこの端末に一時保存しました。';
            } catch (error) {
                status.textContent = '';
            }
        }, 300);
    }

    function restoreDraft() {
        try {
            const raw = localStorage.getItem(storageKey);
            if (!raw) return;
            const draft = JSON.parse(raw);
            if (!draft.savedAt || Date.now() - draft.savedAt > 24 * 60 * 60 * 1000) {
                localStorage.removeItem(storageKey);
                return;
            }
            Object.entries(draft.data || {}).forEach(([key, value]) => {
                const fields = form.querySelectorAll(`[name="${CSS.escape(key)}"], [name="${CSS.escape(key)}[]"]`);
                fields.forEach((field) => {
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        field.checked = Array.isArray(value) ? value.includes(field.value) : String(value) === field.value || value === true;
                    } else {
                        field.value = value;
                    }
                });
            });
            if (typeof draft.idempotencyKey === 'string') idempotencyKey = draft.idempotencyKey;
            updateConditionals();
            showStep(Number(draft.currentStep) || 1);
            status.textContent = '前回の入力内容を復元しました。';
        } catch (error) {
            localStorage.removeItem(storageKey);
        }
    }

    async function refreshNonce() {
        const response = await fetch(config.tokenEndpoint, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('nonce');
        const payload = await response.json();
        nonce = payload.nonce;
    }

    function applyServerErrors(fields) {
        const errors = [];
        Object.entries(fields || {}).forEach(([name, message]) => {
            const field = form.elements[name];
            const target = field instanceof RadioNodeList ? field[0] : field;
            if (!target) return;
            setFieldError(target, String(message));
            errors.push({ field: target, message: String(message) });
        });
        if (errors.length) {
            const firstStep = Number(errors[0].field.closest('[data-step]')?.dataset.step || currentStep);
            showStep(firstStep);
            showErrorSummary(errors);
            errors[0].field.focus();
        }
    }

    async function submitOrder(retryNonce = true) {
        if (submitting || !validateStep(5)) return;
        submitting = true;
        submitButton.disabled = true;
        submitButton.textContent = '送信しています…';
        status.textContent = '安全に送信しています。画面を閉じないでください。';
        hideErrorSummary();

        try {
            const response = await fetch(config.endpoint, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce, 'Idempotency-Key': idempotencyKey },
                body: JSON.stringify(formObject())
            });
            const payload = await response.json();
            if (response.status === 403 && ['jat_expired_nonce', 'rest_cookie_invalid_nonce'].includes(payload.code) && retryNonce) {
                await refreshNonce();
                submitting = false;
                submitButton.disabled = false;
                await submitOrder(false);
                return;
            }
            if (!response.ok) {
                if (payload.data && payload.data.fields) applyServerErrors(payload.data.fields);
                throw new Error(payload.message || '送信できませんでした。入力内容を保持しています。時間をおいて再度お試しください。');
            }
            localStorage.removeItem(storageKey);
            form.hidden = true;
            root.querySelector('.jat-reservation__progress').hidden = true;
            root.querySelector('.jat-reservation__intro').hidden = true;
            successPanel.querySelector('[data-success-message]').textContent = payload.message;
            successPanel.querySelector('[data-reference]').textContent = payload.reference;
            successPanel.hidden = false;
            successPanel.focus();
        } catch (error) {
            status.textContent = error.message || '通信エラーが発生しました。入力内容はこの端末に保持されています。';
            saveDraft();
        } finally {
            submitting = false;
            submitButton.disabled = false;
            submitButton.textContent = 'この内容で申し込む';
        }
    }

    nextButton.addEventListener('click', () => {
        if (!validateStep(currentStep)) return;
        showStep(currentStep + 1);
        saveDraft();
    });
    previousButton.addEventListener('click', () => showStep(currentStep - 1));
    form.addEventListener('submit', (event) => { event.preventDefault(); submitOrder(); });
    form.addEventListener('input', (event) => {
        clearFieldError(event.target);
        if (['service_type', 'customer_type', 'contract_customer', 'transport_type'].includes(event.target.name)) updateConditionals();
        if (['signboard_text', 'signboard_company'].includes(event.target.name)) updateSignPreview();
        saveDraft();
    });
    form.addEventListener('change', (event) => {
        if (['service_type', 'customer_type', 'contract_customer', 'transport_type'].includes(event.target.name)) updateConditionals();
        saveDraft();
    });
    root.querySelector('[data-action="new-application"]').addEventListener('click', () => {
        form.reset();
        form.elements.form_started_at.value = Math.floor(Date.now() / 1000);
        idempotencyKey = createUuid();
        successPanel.hidden = true;
        form.hidden = false;
        root.querySelector('.jat-reservation__progress').hidden = false;
        root.querySelector('.jat-reservation__intro').hidden = false;
        updateConditionals();
        showStep(1);
    });

    updateConditionals();
    restoreDraft();
    showStep(currentStep);
})();
