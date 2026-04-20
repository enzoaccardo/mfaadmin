document.addEventListener('DOMContentLoaded', function () {
    var data = window.mfaSetupData;
    if (!data) return;

    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Alert banner
    var banner = document.createElement('div');
    banner.id = 'mfa-setup-banner';
    banner.className = 'alert alert-warning d-flex align-items-center mb-0';
    banner.style.cssText = 'position:sticky;top:0;z-index:1050;border-radius:0;gap:.75rem;';
    banner.innerHTML =
        '<i class="material-icons" style="font-size:1.4rem">security</i>' +
        '<span><strong>Sicurezza:</strong> Devi configurare l\'autenticazione a due fattori per proteggere il tuo account.</span>' +
        '<button type="button" id="mfa-setup-open" class="btn btn-sm btn-warning ml-auto">Configura ora</button>';

    var wrap = document.querySelector('#main-div') || document.querySelector('.main-page-container') || document.body;
    wrap.insertBefore(banner, wrap.firstChild);

    // Modal
    document.body.insertAdjacentHTML('beforeend',
        '<div class="modal fade" id="mfaSetupModal" tabindex="-1" role="dialog">' +
            '<div class="modal-dialog modal-dialog-centered" role="document">' +
                '<div class="modal-content">' +
                    '<div class="modal-header">' +
                        '<h5 class="modal-title"><i class="material-icons mr-2" style="vertical-align:middle">security</i>Configura autenticazione a due fattori</h5>' +
                        '<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>' +
                    '</div>' +
                    '<div class="modal-body text-center">' +
                        '<div id="mfa-modal-error" class="alert alert-danger d-none" role="alert"></div>' +
                        '<p class="text-muted mb-3">Scansiona il QR code con Google Authenticator, Authy o qualsiasi app TOTP compatibile.</p>' +
                        '<div id="mfa-qr-container" class="mb-3" style="display:inline-block;padding:8px;background:#fff;border:1px solid #dee2e6;border-radius:6px;"></div>' +
                        '<p class="text-muted small mb-1">Non riesci a scansionare? Inserisci manualmente questo codice:</p>' +
                        '<div class="mb-4" style="font-family:monospace;background:#f8f9fa;border:1px solid #dee2e6;border-radius:6px;padding:.5rem 1rem;letter-spacing:.12em;">' +
                            escHtml(data.secret) +
                        '</div>' +
                        '<div class="form-group text-left">' +
                            '<label class="font-weight-bold">Conferma con il codice a 6 cifre</label>' +
                            '<input type="text" id="mfa-setup-code" class="form-control form-control-lg text-center" ' +
                                   'placeholder="000000" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code">' +
                            '<small class="form-text text-muted">Inserisci il codice mostrato dall\'app per attivare MFA.</small>' +
                        '</div>' +
                    '</div>' +
                    '<div class="modal-footer">' +
                        '<button type="button" class="btn btn-secondary" data-dismiss="modal">Annulla</button>' +
                        '<button type="button" class="btn btn-primary" id="mfa-setup-submit">Attiva MFA</button>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>'
    );

    // Inserimento SVG tramite DOMParser per evitare iniezione di markup arbitrario
    var qrContainer = document.getElementById('mfa-qr-container');
    if (qrContainer && typeof data.qrSvg === 'string' && data.qrSvg.indexOf('<script') === -1) {
        var parser = new DOMParser();
        var svgDoc = parser.parseFromString(data.qrSvg, 'image/svg+xml');
        var svgEl  = svgDoc.querySelector('svg');
        if (svgEl) {
            qrContainer.appendChild(document.adoptNode(svgEl));
        }
    }

    document.getElementById('mfa-setup-open').addEventListener('click', function () {
        $('#mfaSetupModal').modal('show');
    });

    $('#mfaSetupModal').on('shown.bs.modal', function () {
        document.getElementById('mfa-setup-code').focus();
    });

    document.getElementById('mfa-setup-submit').addEventListener('click', submitSetup);
    document.getElementById('mfa-setup-code').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') submitSetup();
    });

    function submitSetup() {
        var code    = document.getElementById('mfa-setup-code').value.trim();
        var btn     = document.getElementById('mfa-setup-submit');
        var errDiv  = document.getElementById('mfa-modal-error');

        errDiv.classList.add('d-none');

        if (!/^\d{6}$/.test(code)) {
            errDiv.textContent = 'Inserisci un codice di 6 cifre.';
            errDiv.classList.remove('d-none');
            return;
        }

        btn.disabled    = true;
        btn.textContent = 'Verifica in corso…';

        var body = new FormData();
        body.append('code', code);

        fetch(data.ajaxUrl + '&action=setupVerify', {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body: body,
        })
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (json.success) {
                window.location.href = json.redirect;
            } else {
                errDiv.textContent = json.error || 'Codice non valido. Riprova.';
                errDiv.classList.remove('d-none');
                btn.disabled    = false;
                btn.textContent = 'Attiva MFA';
                document.getElementById('mfa-setup-code').select();
            }
        })
        .catch(function () {
            errDiv.textContent = 'Errore di rete. Riprova.';
            errDiv.classList.remove('d-none');
            btn.disabled    = false;
            btn.textContent = 'Attiva MFA';
        });
    }
});