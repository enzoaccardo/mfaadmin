/**
 * Registrazione e rimozione passkey nella pagina profilo MFA.
 */

function base64urlToUint8Array(str) {
    const base64 = str.replace(/-/g, '+').replace(/_/g, '/');
    const padded  = base64.padEnd(base64.length + (4 - base64.length % 4) % 4, '=');
    return Uint8Array.from(atob(padded), c => c.charCodeAt(0));
}

function uint8ArrayToBase64url(buffer) {
    const bytes = buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer);
    return btoa(String.fromCharCode(...bytes))
        .replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

function prepareCreationOptions(json) {
    const opts = JSON.parse(JSON.stringify(json));
    opts.challenge = base64urlToUint8Array(json.challenge);
    opts.user.id   = base64urlToUint8Array(json.user.id);
    if (opts.excludeCredentials) {
        opts.excludeCredentials = opts.excludeCredentials.map(c => ({
            ...c, id: base64urlToUint8Array(c.id),
        }));
    }
    return opts;
}

function serializeAttestationResponse(cred) {
    return {
        id:    cred.id,
        rawId: uint8ArrayToBase64url(new Uint8Array(cred.rawId)),
        type:  cred.type,
        response: {
            clientDataJSON:    uint8ArrayToBase64url(new Uint8Array(cred.response.clientDataJSON)),
            attestationObject: uint8ArrayToBase64url(new Uint8Array(cred.response.attestationObject)),
        },
    };
}

async function registerPasskey(label) {
    // 1. Opzioni di registrazione
    const optRes = await fetch(window.__PASSKEY_OPTIONS_URL__, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    '{}',
    });

    const optData = await optRes.json();
    if (!optRes.ok) throw new Error(optData.error || 'Errore nel recupero delle opzioni.');

    const { success: _s, ...optionsJson } = optData;

    // 2. WebAuthn API
    let credential;
    try {
        credential = await navigator.credentials.create({
            publicKey: prepareCreationOptions(optionsJson),
        });
    } catch (e) {
        throw new Error('Autenticazione annullata o non supportata: ' + e.message);
    }

    if (!credential) throw new Error('Nessuna credenziale ottenuta dal browser.');

    // 3. Registra sul server
    const payload = { label, ...serializeAttestationResponse(credential) };

    const regRes = await fetch(window.__PASSKEY_REGISTER_URL__, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload),
    });

    const result = await regRes.json();
    if (!regRes.ok) throw new Error(result.error || 'Registrazione fallita.');

    return result.passkey;
}

async function deletePasskey(passkeyId) {
    const url = window.__PASSKEY_DELETE_BASE_URL__ + passkeyId;

    const res = await fetch(url, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    '{}',
    });

    const result = await res.json();
    if (!res.ok) throw new Error(result.error || 'Eliminazione fallita.');
}

document.addEventListener('DOMContentLoaded', () => {

    // Bottone "Aggiungi passkey"
    const registerBtn = document.getElementById('passkey-register-btn');
    if (registerBtn) {
        registerBtn.addEventListener('click', async () => {
            const labelInput = document.getElementById('passkey-label');
            const label      = labelInput ? labelInput.value.trim() : '';
            const statusEl   = document.getElementById('passkey-register-status');

            registerBtn.disabled = true;
            if (statusEl) statusEl.textContent = 'Registrazione in corso…';

            try {
                await registerPasskey(label || 'Dispositivo');
                window.location.reload();
            } catch (e) {
                if (statusEl) {
                    statusEl.textContent = e.message;
                    statusEl.className   = 'text-danger small mt-1 d-block';
                }
                registerBtn.disabled = false;
            }
        });
    }

    // Bottoni "Rimuovi"
    document.querySelectorAll('[data-passkey-delete]').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('Rimuovere questa passkey? Non potrai più usarla per accedere.')) return;

            const id = btn.dataset.passkeyDelete;
            btn.disabled = true;

            try {
                await deletePasskey(id);
                const row = document.getElementById('passkey-row-' + id);
                if (row) row.remove();
            } catch (e) {
                alert(e.message);
                btn.disabled = false;
            }
        });
    });

});