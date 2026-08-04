(function () {
    const body = document.body;
    const sidebar = document.getElementById('appSidebar');
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const mobileMenuButton = document.querySelector('[data-mobile-menu]');
    const mobileBackdrop = document.querySelector('[data-sidebar-close]');
    const motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
    const prefersReducedMotion = motionPreference.matches;
    let currentPreviewUrl = null;
    let deferredInstallPrompt = null;
    let isPageTransitioning = false;

    body.classList.add('js-enhanced');
    initAppMotion();

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            body.classList.add('is-ready');
        });
    });

    initPwaInstall();

    window.addEventListener('pageshow', () => {
        isPageTransitioning = false;
        body.classList.remove('is-navigating');
    });

    if (localStorage.getItem('portal.sidebar.collapsed') === 'true') {
        body.classList.add('sidebar-collapsed');
        sidebar?.classList.add('is-collapsed');
    }

    sidebarToggle?.addEventListener('click', () => {
        body.classList.toggle('sidebar-collapsed');
        sidebar?.classList.toggle('is-collapsed', body.classList.contains('sidebar-collapsed'));
        localStorage.setItem('portal.sidebar.collapsed', body.classList.contains('sidebar-collapsed') ? 'true' : 'false');
    });

    mobileMenuButton?.addEventListener('click', () => {
        body.classList.add('mobile-menu-open');
    });

    mobileBackdrop?.addEventListener('click', () => {
        body.classList.remove('mobile-menu-open');
    });

    document.querySelectorAll('.nav-link-item').forEach((link) => {
        link.addEventListener('click', () => {
            body.classList.remove('mobile-menu-open');
        });
    });

    document.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const href = link.getAttribute('href') || '';
            const url = new URL(link.href, window.location.href);
            const opensElsewhere = link.target === '_blank' || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;
            const isDownload = link.hasAttribute('download') || href.includes('/pdf/download');
            const isHashOnly = url.pathname === window.location.pathname && url.hash;
            const isSamePage = url.pathname === window.location.pathname && url.search === window.location.search && ! url.hash;

            if (opensElsewhere || isDownload || isHashOnly || isSamePage || url.origin !== window.location.origin) {
                return;
            }

            if (prefersReducedMotion || isPageTransitioning || link.dataset.transitioning === 'true') {
                return;
            }

            event.preventDefault();
            isPageTransitioning = true;
            link.dataset.transitioning = 'true';
            body.classList.add('is-navigating');

            window.setTimeout(() => {
                window.location.href = link.href;
            }, 380);
        });
    });

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.submitting === 'true') {
                event.preventDefault();

                return;
            }

            if (typeof form.checkValidity === 'function' && ! form.checkValidity()) {
                return;
            }

            const message = event.submitter?.dataset.confirm || form.dataset.confirm;

            if (message && ! confirm(message)) {
                event.preventDefault();

                return;
            }

            const submitter = event.submitter;
            form.dataset.submitting = 'true';

            if (submitter instanceof HTMLButtonElement) {
                submitter.disabled = true;
                submitter.setAttribute('aria-busy', 'true');

            }

            startFormSubmitLoading(form, submitter);
            updateActionFeedback(form, submitter);

            if (form.dataset.submitLoadingMessage && window.fetch && window.FormData) {
                event.preventDefault();
                submitFormWithFetch(form, submitter);
            }

        });

        form.addEventListener('input', () => clearFormSubmitError(form));
        form.addEventListener('change', () => clearFormSubmitError(form));
    });

    function updateActionFeedback(form, submitter) {
        const panel = form.closest('.invoice-detail-page')?.querySelector('[data-action-feedback-panel]');

        if (! panel) {
            return;
        }

        const title = panel.querySelector('[data-action-feedback-title]');
        const text = panel.querySelector('[data-action-feedback-text]');
        const buttonLabel = submitter?.textContent?.trim()?.replace(/\s+/g, ' ');
        const message = submitter?.dataset.actionFeedback
            || form.dataset.actionFeedback
            || (buttonLabel ? `Executando: ${buttonLabel}...` : 'Processando acao da nota...');

        panel.classList.remove('is-idle', 'is-success', 'is-error');
        panel.classList.add('is-visible', 'is-active');

        if (title) {
            title.textContent = 'Processando acao';
        }

        if (text) {
            text.textContent = message;
        }
    }

    document.querySelectorAll('[data-bs-toggle="tab"]').forEach((tab) => {
        tab.addEventListener('show.bs.tab', (event) => {
            const tabList = event.target.closest('[role="tablist"]');
            tabList?.classList.add('is-switching');
        });

        tab.addEventListener('shown.bs.tab', (event) => {
            const tabList = event.target.closest('[role="tablist"]');
            tabList?.classList.remove('is-switching');

            const targetSelector = event.target.dataset.bsTarget || event.target.getAttribute('href');

            if (targetSelector) {
                playMotion(document.querySelector(targetSelector), 'motion-tab-reveal');
            }
        });
    });

    document.querySelectorAll('.modal').forEach((modal) => {
        modal.addEventListener('show.bs.modal', () => modal.classList.add('is-opening'));
        modal.addEventListener('shown.bs.modal', () => modal.classList.remove('is-opening'));
        modal.addEventListener('hidden.bs.modal', () => modal.classList.remove('is-opening', 'is-closing'));
        modal.addEventListener('hide.bs.modal', () => modal.classList.add('is-closing'));
    });

    document.querySelectorAll('[data-file-input]').forEach((input) => {
        const target = document.querySelector(input.dataset.fileTarget);
        const form = input.closest('form');

        input.addEventListener('change', () => {
            if (! target) {
                return;
            }

            target.textContent = input.files?.[0]?.name || 'Nenhum arquivo selecionado.';
            updateInlinePdfPreview(form, input.files?.[0] || null);
        });
    });

    document.querySelectorAll('[data-pdf-preview-form]').forEach((form) => {
        const refreshPreview = () => {
            const file = form.querySelector('input[type="file"][name="pdf"]')?.files?.[0] || null;
            updateInlinePdfPreview(form, file);
        };

        form.addEventListener('input', refreshPreview);
        form.addEventListener('change', refreshPreview);
    });

    document.querySelectorAll('[data-copy-text]').forEach((button) => {
        button.addEventListener('click', async () => {
            const text = button.dataset.copyText || '';

            if (! text) {
                return;
            }

            await copyText(text);

            const icon = button.querySelector('i');
            const originalClass = icon?.className;

            if (icon) {
                icon.className = 'bi bi-check2';
                window.setTimeout(() => {
                    icon.className = originalClass || 'bi bi-clipboard';
                }, 1200);
            }
        });
    });

    document.querySelectorAll('[data-pdf-annotator]').forEach(initPdfAnnotator);

    document.querySelectorAll('[data-digits-only]').forEach((input) => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/g, '');
        });
    });

    document.querySelectorAll('[data-document-type-select]').forEach((select) => {
        const form = select.closest('form');
        const label = form?.querySelector('[data-reference-label]');
        const field = form?.querySelector('[data-reference-field]');
        const input = form?.querySelector('[name="purchase_order_number"]');

        const syncReferenceLabel = () => {
            if (! label || ! field || ! input) {
                return;
            }

            const withoutPurchaseOrder = select.value === 'nf_no_oc';
            label.textContent = select.value === 'cte' ? 'Nota Fiscal' : 'Ordem de compra';
            field.hidden = withoutPurchaseOrder;
            input.disabled = withoutPurchaseOrder;
            input.required = ! withoutPurchaseOrder;

            if (withoutPurchaseOrder) {
                input.value = '';
            }
        };

        select.addEventListener('change', syncReferenceLabel);
        syncReferenceLabel();
    });

    document.querySelectorAll('[data-payment-method]').forEach((select) => {
        const form = select.closest('form');
        const area = form?.querySelector('[data-payment-installments-area]');
        const countInput = form?.querySelector('[data-installments-count]');
        const grid = form?.querySelector('[data-installments-grid]');

        const syncInstallments = () => {
            if (! area || ! countInput || ! grid) {
                return;
            }

            const requiresInstallments = select.value !== 'anticipated';
            area.hidden = ! requiresInstallments;
            countInput.disabled = ! requiresInstallments;
            countInput.required = requiresInstallments;

            const count = Math.max(1, Math.min(12, Number.parseInt(countInput.value || '1', 10) || 1));
            countInput.value = String(count);

            while (grid.children.length < count) {
                grid.appendChild(createInstallmentRow(grid.children.length));
            }

            while (grid.children.length > count) {
                grid.lastElementChild?.remove();
            }

            grid.querySelectorAll('[data-installment-row]').forEach((row, index) => {
                row.querySelector('.installment-number').textContent = `#${index + 1}`;

                const dueDate = row.querySelector('[data-installment-due-date]');
                if (dueDate) {
                    dueDate.name = `payment_installments[${index}][due_date]`;
                    dueDate.id = `payment_installments_${index}_due_date`;
                    dueDate.min = minimumBusinessDueDate();
                    dueDate.disabled = ! requiresInstallments;
                    dueDate.required = requiresInstallments;
                    row.querySelector('label[for$="_due_date"]')?.setAttribute('for', dueDate.id);
                }
            });
        };

        select.addEventListener('change', syncInstallments);
        countInput?.addEventListener('change', syncInstallments);
        syncInstallments();
    });

    function initAppMotion() {
        const motionSelectors = [
            '.app-alert',
            '.panel',
            '.metric-card',
            '.folder-link',
            '.settings-item',
            '.summary-card-grid > div',
            '.po-summary-grid > div',
            '.attachment-item',
            '.inline-alert',
            '.alert-card',
            '.empty-state',
            '.data-table tbody tr',
        ];

        const markMotionItems = (root = document) => {
            const items = root.querySelectorAll(motionSelectors.join(','));

            items.forEach((item, index) => {
                if (item.dataset.motionReady === 'true') {
                    return;
                }

                item.dataset.motionReady = 'true';
                item.classList.add('motion-item');
                item.style.setProperty('--motion-order', String(Math.min(index, 10)));
            });
        };

        markMotionItems();

        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (! (node instanceof HTMLElement)) {
                        return;
                    }

                    markMotionItems(node);
                    playMotion(node, 'motion-soft-reveal');
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    function playMotion(element, className) {
        if (! element || prefersReducedMotion) {
            return;
        }

        element.classList.remove(className);
        void element.offsetWidth;
        element.classList.add(className);

        window.setTimeout(() => {
            element.classList.remove(className);
        }, 520);
    }

    function updateInlinePdfPreview(form, file) {
        const preview = document.querySelector('[data-inline-pdf-preview]');
        const frame = preview?.querySelector('[data-pdf-preview-frame]');

        if (! form || ! preview || ! frame) {
            return;
        }

        if (! file) {
            preview.hidden = true;
            delete preview.dataset.fileSignature;
            frame.removeAttribute('src');

            if (currentPreviewUrl) {
                URL.revokeObjectURL(currentPreviewUrl);
                currentPreviewUrl = null;
            }

            return;
        }

        const fileSignature = `${file.name}:${file.size}:${file.lastModified}`;

        if (preview.dataset.fileSignature !== fileSignature) {
            if (currentPreviewUrl) {
                URL.revokeObjectURL(currentPreviewUrl);
            }

            currentPreviewUrl = URL.createObjectURL(file);
            frame.src = currentPreviewUrl;
            preview.dataset.fileSignature = fileSignature;
        }

        preview.hidden = false;

        setPreviewText(preview, '[data-preview-file-name]', file.name);
        setPreviewText(preview, '[data-preview-file-size]', formatBytes(file.size));
        const documentType = form.querySelector('[name="document_type"]')?.value || 'nf';
        setPreviewText(preview, '[data-preview-reference-label]', documentType === 'cte' ? 'Nota Fiscal' : 'Ordem');
        setPreviewText(preview, '[data-preview-purchase-order]', form.querySelector('[name="purchase_order_number"]')?.value || '-');
        setPreviewText(preview, '[data-preview-arrival-date]', formatDate(form.querySelector('[name="arrival_date"]')?.value));
        setPreviewText(preview, '[data-preview-payment]', formatPayment(form));
        setPreviewText(preview, '[data-preview-notes]', form.querySelector('[name="user_notes"]')?.value || '-');
    }

    function startFormSubmitLoading(form, submitter) {
        const loadingState = form.querySelector('.submit-loading-state');
        const message = form.dataset.submitLoadingMessage || 'Processando...';

        form.classList.remove('submit-success', 'submit-error');
        form.setAttribute('aria-busy', 'true');

        if (form.dataset.submitLoadingMessage || loadingState) {
            form.classList.add('is-submitting');
        }

        if (loadingState) {
            const loadingText = form.querySelector('[data-submit-loading-text]');
            loadingState.classList.remove('is-success', 'is-error');

            if (loadingText) {
                loadingText.textContent = message;
            }

            loadingState.hidden = false;
        }

        if (submitter instanceof HTMLButtonElement) {
            submitter.dataset.originalHtml = submitter.innerHTML;
            submitter.dataset.originalWidth = String(Math.ceil(submitter.getBoundingClientRect().width));
            submitter.style.minWidth = `${submitter.dataset.originalWidth}px`;
            submitter.classList.remove('is-success', 'is-error');
            submitter.classList.add('is-loading');
            submitter.setAttribute('data-loading-label', message);
        }

        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            if (button !== submitter) {
                button.disabled = true;
            }
        });
    }

    async function submitFormWithFetch(form, submitter) {
        const startedAt = performance.now();
        const action = submitter?.hasAttribute('formaction') ? submitter.formAction : form.action;
        const method = (
            submitter?.hasAttribute('formmethod') ? submitter.formMethod : (form.method || 'POST')
        ).toUpperCase();

        try {
            const response = await fetch(action, {
                method,
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await response.json().catch(() => ({}));

            await waitForMinimumFeedbackTime(startedAt, 650);

            if (! response.ok) {
                const validationMessage = Object.values(payload.errors || {}).flat()[0];
                throw new Error(validationMessage || payload.message || 'Nao foi possivel concluir. Tente novamente.');
            }

            const successMessage = form.dataset.submitSuccessMessage || payload.message || 'Salvo com sucesso. Abrindo...';
            setFormSubmitResult(form, submitter, 'success', successMessage);
            await delay(prefersReducedMotion ? 80 : 720);
            window.location.assign(payload.redirect || response.url || action);
        } catch (error) {
            await waitForMinimumFeedbackTime(startedAt, 650);
            const errorMessage = error instanceof TypeError
                ? (form.dataset.submitErrorMessage || 'Falha de conexao. Tente novamente.')
                : error instanceof Error && error.message
                ? error.message
                : (form.dataset.submitErrorMessage || 'Nao foi possivel concluir. Tente novamente.');
            setFormSubmitResult(form, submitter, 'error', errorMessage);
        }
    }

    function setFormSubmitResult(form, submitter, state, message) {
        const loadingState = form.querySelector('.submit-loading-state');
        const loadingText = form.querySelector('[data-submit-loading-text]');

        form.classList.remove('is-submitting');
        form.classList.add(`submit-${state}`);
        form.removeAttribute('aria-busy');
        delete form.dataset.submitting;

        if (loadingState) {
            loadingState.hidden = false;
            loadingState.classList.remove('is-success', 'is-error');
            loadingState.classList.add(`is-${state}`);
        }

        if (loadingText) {
            loadingText.textContent = message;
        }

        if (submitter instanceof HTMLButtonElement) {
            submitter.classList.remove('is-loading', 'is-success', 'is-error');
            submitter.removeAttribute('aria-busy');

            if (state === 'error') {
                submitter.disabled = false;
                submitter.style.minWidth = '';
            } else {
                submitter.classList.add(`is-${state}`);
            }
        }

        if (state === 'error') {
            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = false;
            });
        }
    }

    function clearFormSubmitError(form) {
        if (form.dataset.submitting === 'true' || ! form.classList.contains('submit-error')) {
            return;
        }

        const loadingState = form.querySelector('.submit-loading-state');

        form.classList.remove('submit-error');

        if (loadingState) {
            loadingState.hidden = true;
            loadingState.classList.remove('is-error');
        }

        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = false;
            button.classList.remove('is-error', 'is-loading');
            button.removeAttribute('aria-busy');
            button.style.minWidth = '';
        });
    }

    function waitForMinimumFeedbackTime(startedAt, minimumDuration) {
        return delay(Math.max(0, minimumDuration - (performance.now() - startedAt)));
    }

    function delay(duration) {
        return new Promise((resolve) => window.setTimeout(resolve, duration));
    }

    function setPreviewText(root, selector, value) {
        const element = root.querySelector(selector);

        if (element) {
            element.textContent = value || '-';
        }
    }

    async function copyText(text) {
        if (navigator.clipboard?.writeText) {
            try {
                await navigator.clipboard.writeText(text);

                return;
            } catch (error) {
                // Fall back to the textarea copy path below.
            }
        }

        const input = document.createElement('textarea');
        input.value = text;
        input.setAttribute('readonly', '');
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        input.remove();
    }

    function formatBytes(bytes) {
        if (! bytes) {
            return '-';
        }

        const units = ['B', 'KB', 'MB', 'GB'];
        let value = bytes;
        let unitIndex = 0;

        while (value >= 1024 && unitIndex < units.length - 1) {
            value /= 1024;
            unitIndex++;
        }

        return `${value.toLocaleString('pt-BR', {
            maximumFractionDigits: unitIndex === 0 ? 0 : 2,
        })} ${units[unitIndex]}`;
    }

    function formatDate(value) {
        if (! value) {
            return '-';
        }

        const [year, month, day] = value.split('-');

        if (! year || ! month || ! day) {
            return value;
        }

        return `${day}/${month}/${year}`;
    }

    function createInstallmentRow(index) {
        const row = document.createElement('div');
        row.className = 'installment-row';
        row.dataset.installmentRow = '';
        row.innerHTML = `
            <div class="installment-number">#${index + 1}</div>
            <div>
                <label class="form-label" for="payment_installments_${index}_due_date">Vencimento</label>
                <input class="form-control" id="payment_installments_${index}_due_date" type="date" name="payment_installments[${index}][due_date]" data-installment-due-date>
            </div>
        `;

        return row;
    }

    function minimumBusinessDueDate() {
        const date = new Date();
        date.setHours(0, 0, 0, 0);

        let businessDays = 0;

        while (businessDays < 2) {
            date.setDate(date.getDate() + 1);

            const day = date.getDay();

            if (day !== 0 && day !== 6) {
                businessDays++;
            }
        }

        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }

    function formatPayment(form) {
        const method = form.querySelector('[name="payment_method"]')?.value || 'anticipated';

        if (method === 'anticipated') {
            return 'Antecipado';
        }

        const label = method === 'boleto' ? 'Boleto' : 'Deposito';
        const count = form.querySelector('[name="payment_installments_count"]')?.value || '1';

        return `${label} - ${count} parcela${count === '1' ? '' : 's'}`;
    }

    async function initPdfAnnotator(root) {
        const pdfjs = window.pdfjsLib;
        const pagesRoot = root.querySelector('[data-pdf-pages]');
        const status = root.querySelector('[data-annotation-status]');
        const canAnnotate = (root.dataset.canAnnotate || '').trim() === 'true';
        const pdfUrl = root.dataset.pdfUrl;
        const saveUrl = root.dataset.saveUrl;
        const state = {
            strokes: parseAnnotationData(root.dataset.annotations).strokes || [],
            pages: new Map(),
            drawing: null,
            tool: 'pen',
            dirty: false,
        };

        root.classList.toggle('is-readonly', ! canAnnotate);

        if (! pdfjs || ! pagesRoot || ! pdfUrl) {
            setAnnotationStatus(status, 'Nao foi possivel carregar o visualizador.');

            return;
        }

        pdfjs.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        bindAnnotationToolbar(root, state, canAnnotate, saveUrl, status);

        try {
            const pdf = await pdfjs.getDocument(pdfUrl).promise;
            pagesRoot.innerHTML = '';

            for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
                await renderPdfPage(pdf, pageNumber, pagesRoot, state, canAnnotate);
            }

            setAnnotationStatus(status, canAnnotate ? 'Pronto para marcar.' : '');
        } catch (error) {
            pagesRoot.innerHTML = '<div class="pdf-loading-state">Nao foi possivel carregar o PDF.</div>';
            setAnnotationStatus(status, 'Falha ao carregar PDF.');
        }
    }

    function bindAnnotationToolbar(root, state, canAnnotate, saveUrl, status) {
        if (! canAnnotate) {
            return;
        }

        root.querySelectorAll('[data-annotation-tool]').forEach((button) => {
            button.addEventListener('click', () => {
                state.tool = button.dataset.annotationTool || 'pen';

                root.querySelectorAll('[data-annotation-tool]').forEach((toolButton) => {
                    const active = toolButton === button;
                    toolButton.classList.toggle('active', active);
                    toolButton.classList.toggle('btn-primary', active);
                    toolButton.classList.toggle('btn-outline-primary', ! active);
                    toolButton.setAttribute('aria-pressed', active ? 'true' : 'false');
                });

                setAnnotationStatus(status, annotationToolLabel(state.tool));
            });
        });

        root.querySelector('[data-annotation-undo]')?.addEventListener('click', () => {
            state.strokes.pop();
            state.dirty = true;
            redrawAllAnnotations(state);
            setAnnotationStatus(status, 'Alteracao pendente.');
        });

        root.querySelector('[data-annotation-clear]')?.addEventListener('click', () => {
            if (! confirm('Limpar todos os rabiscos desta nota?')) {
                return;
            }

            state.strokes = [];
            state.dirty = true;
            redrawAllAnnotations(state);
            setAnnotationStatus(status, 'Alteracao pendente.');
        });

        root.querySelector('[data-annotation-save]')?.addEventListener('click', async (event) => {
            const button = event.currentTarget;
            button.disabled = true;
            setAnnotationStatus(status, 'Salvando...');

            try {
                await saveAnnotations(saveUrl, state.strokes);
                state.dirty = false;
                setAnnotationStatus(status, 'Rabiscos salvos.');
            } catch (error) {
                setAnnotationStatus(status, 'Falha ao salvar.');
            } finally {
                button.disabled = false;
            }
        });
    }

    async function renderPdfPage(pdf, pageNumber, pagesRoot, state, canAnnotate) {
        const page = await pdf.getPage(pageNumber);
        const unscaledViewport = page.getViewport({ scale: 1 });
        const pageWidth = Math.min(pagesRoot.clientWidth || 980, 980);
        const scale = pageWidth / unscaledViewport.width;
        const viewport = page.getViewport({ scale });
        const pixelRatio = window.devicePixelRatio || 1;

        const shell = document.createElement('div');
        shell.className = 'pdf-page-shell';
        shell.dataset.page = String(pageNumber);
        shell.style.width = `${viewport.width}px`;

        const pdfCanvas = document.createElement('canvas');
        pdfCanvas.width = Math.floor(viewport.width * pixelRatio);
        pdfCanvas.height = Math.floor(viewport.height * pixelRatio);
        pdfCanvas.style.width = `${viewport.width}px`;
        pdfCanvas.style.height = `${viewport.height}px`;

        const pdfContext = pdfCanvas.getContext('2d');
        pdfContext.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);

        const drawCanvas = document.createElement('canvas');
        drawCanvas.className = 'pdf-draw-layer';
        drawCanvas.width = Math.floor(viewport.width * pixelRatio);
        drawCanvas.height = Math.floor(viewport.height * pixelRatio);
        drawCanvas.style.width = `${viewport.width}px`;
        drawCanvas.style.height = `${viewport.height}px`;

        shell.appendChild(pdfCanvas);
        shell.appendChild(drawCanvas);
        pagesRoot.appendChild(shell);

        await page.render({
            canvasContext: pdfContext,
            viewport,
        }).promise;

        state.pages.set(pageNumber, {
            canvas: drawCanvas,
            width: viewport.width,
            height: viewport.height,
            pixelRatio,
        });

        redrawAnnotationsForPage(state, pageNumber);

        if (canAnnotate) {
            bindDrawLayer(drawCanvas, pageNumber, state);
        }
    }

    function bindDrawLayer(canvas, pageNumber, state) {
        canvas.addEventListener('pointerdown', (event) => {
            if (state.tool === 'eraser') {
                eraseNearestStroke(canvas, pageNumber, state, normalizedPoint(canvas, event));

                return;
            }

            canvas.setPointerCapture(event.pointerId);
            state.drawing = {
                page: pageNumber,
                tool: state.tool,
                color: annotationToolColor(state.tool),
                width: annotationToolWidth(state.tool),
                points: [normalizedPoint(canvas, event)],
            };
        });

        canvas.addEventListener('pointermove', (event) => {
            if (! state.drawing || state.drawing.page !== pageNumber) {
                return;
            }

            const point = normalizedPoint(canvas, event);

            if (state.drawing.tool === 'highlight') {
                state.drawing.points = normalizeHighlightPoints(state.drawing.points[0], point);
            } else if (isShapeTool(state.drawing.tool)) {
                state.drawing.points = [state.drawing.points[0], point];
            } else {
                state.drawing.points.push(point);
            }

            redrawAnnotationsForPage(state, pageNumber, state.drawing);
        });

        const finishDrawing = (event) => {
            if (! state.drawing || state.drawing.page !== pageNumber) {
                return;
            }

            if (state.drawing.points.length > 1) {
                state.strokes.push(state.drawing);
                state.dirty = true;
            }

            state.drawing = null;
            canvas.releasePointerCapture?.(event.pointerId);
            redrawAnnotationsForPage(state, pageNumber);
        };

        canvas.addEventListener('pointerup', finishDrawing);
        canvas.addEventListener('pointercancel', finishDrawing);
    }

    function normalizedPoint(canvas, event) {
        const rect = canvas.getBoundingClientRect();

        return {
            x: Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width)),
            y: Math.max(0, Math.min(1, (event.clientY - rect.top) / rect.height)),
        };
    }

    function normalizeHighlightPoints(start, end) {
        const horizontalDistance = Math.abs(end.x - start.x);
        const verticalDistance = Math.abs(end.y - start.y);
        const y = horizontalDistance >= verticalDistance * 1.2
            ? (start.y + end.y) / 2
            : end.y;

        return [
            { x: start.x, y },
            { x: end.x, y },
        ];
    }

    function eraseNearestStroke(canvas, pageNumber, state, point) {
        let nearestIndex = -1;
        let nearestDistance = Number.POSITIVE_INFINITY;

        state.strokes.forEach((stroke, index) => {
            if (Number(stroke.page) !== pageNumber) {
                return;
            }

            const distance = distanceToStroke(point, stroke);

            if (distance < nearestDistance) {
                nearestDistance = distance;
                nearestIndex = index;
            }
        });

        if (nearestIndex >= 0 && nearestDistance <= 0.035) {
            state.strokes.splice(nearestIndex, 1);
            state.dirty = true;
            redrawAnnotationsForPage(state, pageNumber);
        }
    }

    function distanceToStroke(point, stroke) {
        const points = stroke.points || [];

        if (points.length === 1) {
            return distanceBetweenPoints(point, points[0]);
        }

        let distance = Number.POSITIVE_INFINITY;

        for (let index = 0; index < points.length - 1; index++) {
            distance = Math.min(distance, distanceToSegment(point, points[index], points[index + 1]));
        }

        return distance;
    }

    function distanceBetweenPoints(point, target) {
        const dx = point.x - target.x;
        const dy = point.y - target.y;

        return Math.sqrt((dx * dx) + (dy * dy));
    }

    function distanceToSegment(point, start, end) {
        const dx = end.x - start.x;
        const dy = end.y - start.y;
        const lengthSquared = (dx * dx) + (dy * dy);

        if (lengthSquared === 0) {
            return distanceBetweenPoints(point, start);
        }

        const position = Math.max(0, Math.min(1, (((point.x - start.x) * dx) + ((point.y - start.y) * dy)) / lengthSquared));

        return distanceBetweenPoints(point, {
            x: start.x + (position * dx),
            y: start.y + (position * dy),
        });
    }

    function redrawAllAnnotations(state) {
        state.pages.forEach((_, pageNumber) => redrawAnnotationsForPage(state, pageNumber));
    }

    function redrawAnnotationsForPage(state, pageNumber, activeStroke = null) {
        const page = state.pages.get(pageNumber);

        if (! page) {
            return;
        }

        const context = page.canvas.getContext('2d');
        context.setTransform(1, 0, 0, 1, 0, 0);
        context.clearRect(0, 0, page.canvas.width, page.canvas.height);
        context.setTransform(page.pixelRatio, 0, 0, page.pixelRatio, 0, 0);

        state.strokes
            .filter((stroke) => Number(stroke.page) === pageNumber)
            .forEach((stroke) => drawStroke(context, stroke, page.width, page.height));

        if (activeStroke) {
            drawStroke(context, activeStroke, page.width, page.height);
        }
    }

    function drawStroke(context, stroke, width, height) {
        const points = stroke.points || [];

        if (! points.length) {
            return;
        }

        if (isShapeTool(stroke.tool)) {
            drawShape(context, stroke, width, height);

            return;
        }

        context.strokeStyle = stroke.color || '#d92d20';
        context.lineWidth = Number(stroke.width || 3);
        context.lineCap = 'round';
        context.lineJoin = 'round';
        context.globalAlpha = stroke.tool === 'highlight' ? 0.5 : 1;
        context.globalCompositeOperation = stroke.tool === 'highlight' ? 'multiply' : 'source-over';
        context.beginPath();
        context.moveTo(points[0].x * width, points[0].y * height);

        points.slice(1).forEach((point) => {
            context.lineTo(point.x * width, point.y * height);
        });

        context.stroke();
        context.globalAlpha = 1;
        context.globalCompositeOperation = 'source-over';
    }

    function drawShape(context, stroke, width, height) {
        const points = stroke.points || [];

        if (points.length < 2) {
            return;
        }

        const start = {
            x: points[0].x * width,
            y: points[0].y * height,
        };
        const end = {
            x: points[1].x * width,
            y: points[1].y * height,
        };
        const box = {
            x: Math.min(start.x, end.x),
            y: Math.min(start.y, end.y),
            width: Math.abs(end.x - start.x),
            height: Math.abs(end.y - start.y),
        };

        context.strokeStyle = stroke.color || '#d92d20';
        context.lineWidth = Number(stroke.width || 3);
        context.lineCap = 'round';
        context.lineJoin = 'round';
        context.globalAlpha = 1;
        context.globalCompositeOperation = 'source-over';

        if (stroke.tool === 'rectangle') {
            context.strokeRect(box.x, box.y, box.width, box.height);

            return;
        }

        if (stroke.tool === 'ellipse') {
            context.beginPath();
            context.ellipse(
                box.x + (box.width / 2),
                box.y + (box.height / 2),
                Math.max(1, box.width / 2),
                Math.max(1, box.height / 2),
                0,
                0,
                Math.PI * 2
            );
            context.stroke();

            return;
        }

        drawArrow(context, start, end);
    }

    function drawArrow(context, start, end) {
        const angle = Math.atan2(end.y - start.y, end.x - start.x);
        const headLength = 18;

        context.beginPath();
        context.moveTo(start.x, start.y);
        context.lineTo(end.x, end.y);
        context.stroke();

        context.beginPath();
        context.moveTo(end.x, end.y);
        context.lineTo(end.x - (headLength * Math.cos(angle - Math.PI / 6)), end.y - (headLength * Math.sin(angle - Math.PI / 6)));
        context.moveTo(end.x, end.y);
        context.lineTo(end.x - (headLength * Math.cos(angle + Math.PI / 6)), end.y - (headLength * Math.sin(angle + Math.PI / 6)));
        context.stroke();
    }

    function annotationToolLabel(tool) {
        if (tool === 'highlight') {
            return 'Marca texto ativo.';
        }

        if (tool === 'eraser') {
            return 'Borracha ativa.';
        }

        if (tool === 'rectangle') {
            return 'Retangulo ativo.';
        }

        if (tool === 'ellipse') {
            return 'Circulo ativo.';
        }

        if (tool === 'arrow') {
            return 'Seta ativa.';
        }

        return 'Caneta ativa.';
    }

    function isShapeTool(tool) {
        return ['rectangle', 'ellipse', 'arrow'].includes(tool);
    }

    function annotationToolColor(tool) {
        return tool === 'highlight' ? '#ffe45c' : '#d92d20';
    }

    function annotationToolWidth(tool) {
        if (tool === 'highlight') {
            return 22;
        }

        return isShapeTool(tool) ? 4 : 3;
    }

    async function saveAnnotations(url, strokes) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch(url, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ strokes }),
        });

        if (! response.ok) {
            throw new Error('Annotation save failed');
        }
    }

    function parseAnnotationData(value) {
        try {
            return value ? JSON.parse(value) : { strokes: [] };
        } catch (error) {
            return { strokes: [] };
        }
    }

    function setAnnotationStatus(element, message) {
        if (element) {
            element.textContent = message || '';
        }
    }

    function initPwaInstall() {
        const installButtons = document.querySelectorAll('[data-install-app]');
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

        if ('serviceWorker' in navigator && (window.isSecureContext || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/service-worker.js').catch(() => {});
            });
        }

        if (! installButtons.length || isStandalone) {
            return;
        }

        const showInstallButtons = () => {
            installButtons.forEach((button) => {
                button.hidden = false;
            });
        };

        const hideInstallButtons = () => {
            installButtons.forEach((button) => {
                button.hidden = true;
            });
        };

        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            deferredInstallPrompt = event;
            showInstallButtons();
        });

        installButtons.forEach((button) => {
            button.addEventListener('click', async () => {
                if (! deferredInstallPrompt) {
                    return;
                }

                button.setAttribute('aria-busy', 'true');
                deferredInstallPrompt.prompt();

                try {
                    await deferredInstallPrompt.userChoice;
                } finally {
                    deferredInstallPrompt = null;
                    button.removeAttribute('aria-busy');
                    hideInstallButtons();
                }
            });
        });

        window.addEventListener('appinstalled', hideInstallButtons);
    }
})();
