/**
 * KSeF settings: one file picker per section takes the certificate (.crt) and
 * its private key (.key) together and fills the matching textarea for each.
 *
 * Files are read in the browser and never uploaded: the input has no name, so
 * only the PEM text in the textareas reaches the server, as when pasted. A file
 * is told apart by its PEM label; binary DER files by their name (.key, .p8 are
 * keys) and converted to PEM here.
 */
(function () {
	'use strict';

	// OID 1.2.840.113549.1.5.13 (PBES2): marks an encrypted PKCS#8 key in DER.
	var PBES2 = [0x06, 0x09, 0x2a, 0x86, 0x48, 0x86, 0xf7, 0x0d, 0x01, 0x05, 0x0d];

	function contains(bytes, needle) {
		outer: for (var i = 0; i + needle.length <= bytes.length; i++) {
			for (var j = 0; j < needle.length; j++) {
				if (bytes[i + j] !== needle[j]) {
					continue outer;
				}
			}
			return true;
		}
		return false;
	}

	function toPem(bytes, label) {
		var binary = '';
		for (var i = 0; i < bytes.length; i++) {
			binary += String.fromCharCode(bytes[i]);
		}
		var body = window.btoa(binary).replace(/(.{64})/g, '$1\n').replace(/\n$/, '');
		return '-----BEGIN ' + label + '-----\n' + body + '\n-----END ' + label + '-----\n';
	}

	/** {kind: 'certificate'|'key', pem} or null when the file is neither. */
	function read(buffer, name) {
		var bytes = new Uint8Array(buffer);
		var text = new TextDecoder('utf-8').decode(bytes);
		var begin = text.indexOf('-----BEGIN');

		if (begin !== -1) {
			var pem = text.slice(begin).trim() + '\n';
			return { kind: /-----BEGIN [A-Z ]*PRIVATE KEY-----/.test(pem) ? 'key' : 'certificate', pem: pem };
		}

		// DER starts with a SEQUENCE (0x30); anything else is not usable.
		if (bytes.length === 0 || bytes[0] !== 0x30) {
			return null;
		}

		if (/\.(key|p8)$/i.test(name)) {
			return { kind: 'key', pem: toPem(bytes, contains(bytes, PBES2) ? 'ENCRYPTED PRIVATE KEY' : 'PRIVATE KEY') };
		}

		return { kind: 'certificate', pem: toPem(bytes, 'CERTIFICATE') };
	}

	function mark(id, ok, text) {
		var item = document.getElementById(id + '_state');
		if (item) {
			item.textContent = text;
			item.className = ok ? 'is-ok' : 'is-missing';
		}
	}

	document.querySelectorAll('.polski-ksef-files').forEach(function (input) {
		input.addEventListener('change', function () {
			Array.prototype.forEach.call(input.files || [], function (file) {
				file.arrayBuffer().then(function (buffer) {
					var found = read(buffer, file.name);
					if (found === null) {
						mark(input.dataset.key, false, input.dataset.error + ' ' + file.name);
						return;
					}
					var targetId = found.kind === 'key' ? input.dataset.key : input.dataset.cert;
					var target = document.getElementById(targetId);
					if (!target) {
						return;
					}
					target.value = found.pem;
					target.dispatchEvent(new Event('input', { bubbles: true }));
					var item = document.getElementById(targetId + '_state');
					mark(targetId, true, item ? item.dataset.loaded.replace('%s', file.name) : file.name);
				});
			});
			input.value = '';
		});
	});
}());
