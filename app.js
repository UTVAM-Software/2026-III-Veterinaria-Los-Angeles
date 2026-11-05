// ============================================================
// Ayudas para que los formularios sean fáciles de usar
// (el servidor también revisa todo; esto es solo para avisar rápido)
// ============================================================
document.addEventListener('DOMContentLoaded', function () {

    // 1) Campos "solo letras": borra lo que no sea letra mientras se escribe
    document.querySelectorAll('.solo-letras').forEach(function (campo) {
        campo.addEventListener('input', function () {
            this.value = this.value.replace(/[^A-Za-zñÑáéíóúÁÉÍÓÚüÜ\s.'\-]/g, '');
        });
    });

    // 2) Campos "solo números": borra lo que no sea número
    document.querySelectorAll('.solo-numeros').forEach(function (campo) {
        campo.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    });

    // 3) Mensajes de error en español sencillo (en vez de los del navegador)
    document.querySelectorAll('input, textarea, select').forEach(function (campo) {
        campo.addEventListener('invalid', function () {
            var v = this.validity;
            if (v.valueMissing) {
                this.setCustomValidity(this.dataset.vacio || 'Este dato es obligatorio');
            } else if (v.rangeUnderflow || v.rangeOverflow || v.badInput || v.patternMismatch || v.tooShort) {
                this.setCustomValidity(this.dataset.msg || 'Revisa este dato, no está escrito correctamente');
            }
        });
        campo.addEventListener('input', function () { this.setCustomValidity(''); });
        campo.addEventListener('change', function () { this.setCustomValidity(''); });
    });

    // 4) Reloj para elegir la hora (si cargó la librería)
    if (window.flatpickr && document.querySelector('.reloj-input')) {
        flatpickr('.reloj-input', { enableTime: true, noCalendar: true, dateFormat: 'h:i K', time_24hr: false, allowInput: false });
    }

    // 5) Preguntar antes de acciones delicadas (por ejemplo, eliminar)
    document.querySelectorAll('form[data-confirmar]').forEach(function (form) {
        form.addEventListener('submit', function (evento) {
            if (!window.confirm(form.dataset.confirmar)) { evento.preventDefault(); }
        });
    });

    // 6) Evitar que se guarde dos veces si le dan doble clic al botón
    document.querySelectorAll('form[data-evitar-doble]').forEach(function (form) {
        form.addEventListener('submit', function () {
            if (!form.checkValidity()) { return; }
            var boton = form.querySelector('button[type="submit"]');
            if (boton) {
                setTimeout(function () { boton.disabled = true; boton.textContent = 'Guardando... un momento'; }, 0);
            }
        });
    });

    // 7) Mostrar u ocultar la contraseña en el login
    var ver = document.getElementById('ver-clave');
    var clave = document.getElementById('password');
    if (ver && clave) {
        ver.addEventListener('change', function () { clave.type = ver.checked ? 'text' : 'password'; });
    }

    // 8) Calcular cuánto falta por pagar mientras se escribe
    var costo = document.getElementById('costo_total');
    var anticipo = document.getElementById('a_cuenta');
    var aviso = document.getElementById('resta-pago');
    if (costo && anticipo && aviso) {
        var calcular = function () {
            var t = parseFloat(costo.value) || 0;
            var a = parseFloat(anticipo.value) || 0;
            if (a > t) { aviso.textContent = '⚠️ El anticipo no puede ser mayor que el costo total.'; aviso.style.color = '#8f1d1d'; }
            else if (t > 0) { aviso.textContent = 'Falta por pagar: $' + (t - a).toFixed(2); aviso.style.color = ''; }
            else { aviso.textContent = ''; }
        };
        costo.addEventListener('input', calcular);
        anticipo.addEventListener('input', calcular);
        calcular();
    }
});
