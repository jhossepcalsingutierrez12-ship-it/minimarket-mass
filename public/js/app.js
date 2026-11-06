// Minimarket Mass · JavaScript del sistema. Corre en el navegador y solo mejora la pantalla:
// los datos y la seguridad los maneja PHP en el servidor. Cada parte revisa si su
// elemento existe en la página antes de hacer algo.
'use strict';

const $ = sel => document.querySelector(sel);
const $$ = sel => document.querySelectorAll(sel);

// 1. Catálogo: buscar y filtrar sin recargar la página
const grid = document.getElementById('grid');
if (grid) {
  const buscar = document.getElementById('buscar');
  const estado = document.getElementById('filtroEstado');
  const chips = $$('#chips .chip');
  const vacio = document.getElementById('vacio');
  let categoria = '';

  // Los filtros solo sirven con JavaScript, por eso empiezan ocultos
  $$('.solo-js').forEach(el => { el.hidden = false; });

  function filtrar() {
    const q = buscar.value.trim().toLowerCase();
    let visibles = 0;
    grid.querySelectorAll('.prod').forEach(t => {
      const ok = (!categoria || t.dataset.cat === categoria)
              && (!estado.value || t.dataset.estado === estado.value)
              && (!q || t.dataset.nombre.includes(q) || t.dataset.sku.includes(q));
      t.hidden = !ok;
      if (ok) visibles++;
    });
    vacio.hidden = visibles > 0;
  }

  chips.forEach(chip => chip.addEventListener('click', () => {
    categoria = chip.dataset.cat;
    chips.forEach(c => c.classList.toggle('active', c === chip));
    filtrar();
  }));
  buscar.addEventListener('input', filtrar);
  estado.addEventListener('change', filtrar);
}

// 2. Login: botón para ver u ocultar la contraseña
const verClave = document.getElementById('verClave');
if (verClave) {
  const clave = document.getElementById('clave');
  verClave.hidden = false;
  verClave.addEventListener('click', () => {
    const oculta = clave.type === 'password';
    clave.type = oculta ? 'text' : 'password';
    verClave.textContent = oculta ? 'Ocultar' : 'Ver';
  });
}

// 3. Login: cuenta regresiva del bloqueo (PHP pone los segundos en data-espera)
const avisoBloqueo = document.querySelector('.msg[data-espera]');
if (avisoBloqueo) {
  let s = parseInt(avisoBloqueo.dataset.espera, 10);
  const reloj = setInterval(() => {
    s--;
    avisoBloqueo.textContent = 'Demasiados intentos. Espera ' + s + ' segundos y vuelve a intentar.';
    if (s <= 0) { clearInterval(reloj); location.href = '?accion=login'; }
  }, 1000);
}

// 4. Paso 2: el código solo acepta números y "Reenviar" se activa a los 30 segundos
const codigo = document.getElementById('codigo');
if (codigo) {
  codigo.addEventListener('input', () => { codigo.value = codigo.value.replace(/\D/g, '').slice(0, 6); });
  codigo.focus();
}
const reenviar = document.getElementById('reenviar');
if (reenviar && reenviar.disabled) {
  let s = parseInt(reenviar.dataset.espera, 10);
  const t = setInterval(() => {
    s--;
    if (s <= 0) { clearInterval(t); reenviar.disabled = false; reenviar.textContent = 'Reenviar código'; return; }
    reenviar.textContent = 'Reenviar en ' + s + ' s';
  }, 1000);
}

// 5. Reloj en tiempo real de la franja negra: "Martes 03/11/2026 · 10:36:05 a. m." (se actualiza cada segundo)
const relojTop = document.getElementById('reloj');
if (relojTop) {
  const dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
  const p = n => String(n).padStart(2, '0');
  const pintar = () => {
    const d = new Date(), h = d.getHours(), h12 = h % 12 || 12;
    relojTop.textContent = `${dias[d.getDay()]} ${p(d.getDate())}/${p(d.getMonth() + 1)}/${d.getFullYear()} · `
      + `${p(h12)}:${p(d.getMinutes())}:${p(d.getSeconds())} ${h < 12 ? 'a. m.' : 'p. m.'}`;
  };
  pintar(); setInterval(pintar, 1000);
}

// 6. Códigos QR (Yape/Plin y boleta): PHP deja el texto en data-qr y aquí se dibuja
if (window.QR) {
  $$('[data-qr]').forEach(el => {
    el.innerHTML = window.QR.svg(el.dataset.qr, parseInt(el.dataset.qrTam || '120', 10), el.dataset.qrColor || '#000');
  });
}

// 7. Imprimir solo una parte: la hoja del catálogo (imp-hoja) o el comprobante (imp-ticket)
$$('[data-imprimir]').forEach(b => b.addEventListener('click', () => {
  const clase = b.dataset.imprimir;
  document.body.classList.add(clase);
  const quitar = () => document.body.classList.remove(clase);
  window.addEventListener('afterprint', quitar, {once: true});
  window.print();
  setTimeout(quitar, 1000);
}));

// 8. Precio: muestra el subtotal y el IGV mientras se escribe
$$('[data-igv]').forEach(input => {
  const salida = document.getElementById(input.dataset.igv);
  const pintar = () => {
    const v = parseFloat(input.value.replace(',', '.'));
    if (!(v > 0)) { salida.textContent = ''; return; }
    const igv = Math.round(v * 18 / 118 * 100) / 100;
    salida.textContent = `Subtotal S/ ${(v - igv).toFixed(2)} · IGV S/ ${igv.toFixed(2)}`;
  };
  input.addEventListener('input', pintar); pintar();
});

// 9. Foto: vista previa antes de guardar
$$('input[type=file][data-vista]').forEach(input => input.addEventListener('change', () => {
  const caja = document.getElementById(input.dataset.vista), f = input.files[0];
  if (!f) return;
  caja.innerHTML = `<img src="${URL.createObjectURL(f)}" alt=""><span>Foto elegida: se guarda al tocar «Guardar».</span>`;
}));

// 10. Elegir otro producto recarga la página para mostrar sus lotes o su código sugerido
$$('select[data-recargar]').forEach(sel => sel.addEventListener('change', () => {
  const f = document.getElementById(sel.dataset.recargar);
  const campo = document.createElement('input');
  campo.type = 'hidden'; campo.name = 'producto'; campo.value = sel.value;
  f.appendChild(campo); f.submit();
}));
$$('select[data-enviar]').forEach(sel => sel.addEventListener('change', () => sel.form.submit()));

// 11. Nueva venta: buscar producto y revisar el DNI/RUC del cliente mientras se escribe
const filasVenta = document.getElementById('filasVenta');
if (filasVenta) {
  $('#buscarVenta').addEventListener('input', e => {
    const q = e.target.value.trim().toLowerCase();
    filasVenta.querySelectorAll('tr').forEach(tr => { tr.hidden = q !== '' && !tr.dataset.buscar.includes(q); });
  });
  const doc = $('#doc'), tipo = $('#tipo'), info = $('#cliInfo'), nombreWrap = $('#nombreWrap');
  let pedido = 0;
  const revisar = async () => {
    doc.value = doc.value.replace(/\D/g, '');
    const factura = tipo.value === 'FACTURA';
    $('#docLabel').textContent = factura ? 'RUC del cliente *' : 'DNI o RUC del cliente';
    if (doc.value === '') {
      nombreWrap.hidden = true;
      info.innerHTML = factura ? '<span class="err">La factura necesita el RUC del cliente.</span>'
                               : '<span class="hint">Vacío = «Cliente varios». Escribe el DNI si el cliente lo pide en su boleta.</span>';
      return;
    }
    const mio = ++pedido;
    const r = await fetch('?accion=cliente&doc=' + encodeURIComponent(doc.value)).then(x => x.json()).catch(() => null);
    if (!r || mio !== pedido) return;
    const tx = s => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };
    if (r.error) { nombreWrap.hidden = true; info.innerHTML = `<span class="err">${tx(r.error)}</span>`; return; }
    if (!r.tipo) { nombreWrap.hidden = true; info.innerHTML = ''; return; }   // todavía escribiendo
    if (factura && r.tipo !== 'RUC') { nombreWrap.hidden = true; info.innerHTML = '<span class="err">Para factura escribe un RUC de 11 dígitos.</span>'; return; }
    nombreWrap.hidden = !!r.nombre;
    info.innerHTML = r.nombre ? `<span class="hint">✔ ${r.tipo} registrado: <b>${tx(r.nombre)}</b></span>`
                              : `<span class="hint">${r.tipo} nuevo: escribe ${r.tipo === 'RUC' ? 'la razón social' : 'el nombre'} y se registrará al cobrar.</span>`;
  };
  doc.addEventListener('input', revisar);
  tipo.addEventListener('change', revisar);
  nombreWrap.hidden = true;
  revisar();
}

// 12. Cobro en efectivo: billetes rápidos y vuelto en vivo
const recibido = document.getElementById('recibido');
if (recibido) {
  const total = parseFloat($('[data-total]').dataset.total), vuelto = $('#vuelto');
  const calcular = () => {
    const r = parseFloat(recibido.value.replace(',', '.'));
    vuelto.textContent = r >= total ? 'Vuelto: S/ ' + (Math.round((r - total) * 100) / 100).toFixed(2) : 'Vuelto: —';
  };
  recibido.addEventListener('input', calcular);
  $$('[data-billete]').forEach(b => b.addEventListener('click', () => { recibido.value = b.dataset.billete; calcular(); }));
}
const operacion = document.getElementById('operacion');
if (operacion) operacion.addEventListener('input', () => { operacion.value = operacion.value.replace(/\D/g, ''); });

// 13. Verifica que eres humano: la barra gira el logo en pantalla (PHP revisa el ángulo)
const giro = document.getElementById('giro');
if (giro) {
  const img = document.getElementById('captchaImg');
  const girar = () => { img.style.transform = `rotate(${giro.value}deg)`; };
  giro.addEventListener('input', girar);
  girar();
}

// 14. Cuadre de stock: compara el conteo físico con el stock del sistema
const tablaCuadre = document.getElementById('tablaCuadre');
if (tablaCuadre) {
  const total = document.getElementById('noCuadran');
  const revisar = () => {
    let malos = 0, contados = 0;
    tablaCuadre.querySelectorAll('tbody tr').forEach(tr => {
      const v = tr.querySelector('.conteo').value, celda = tr.querySelector('.resultado');
      if (v === '') { celda.innerHTML = '<span class="sub" style="margin:0">Sin contar</span>'; return; }
      contados++;
      const dif = parseInt(v, 10) - parseInt(tr.dataset.sistema, 10);
      if (dif !== 0) malos++;
      celda.innerHTML = dif === 0 ? '<span class="tag ok">Cuadra ✔</span>'
        : `<span class="tag bad">${dif < 0 ? 'Faltan ' + (-dif) : 'Sobran ' + dif}</span>`;
    });
    total.textContent = contados ? malos : '—';
  };
  tablaCuadre.addEventListener('input', revisar);
}

// 15. Abrir caja al entrar: al elegir la tienda, solo quedan sus cajas
const radiosTienda = document.querySelectorAll('input[name="tienda"]');
if (radiosTienda.length && document.getElementById('caja')) {
  const caja = document.getElementById('caja');
  const filtrar = () => {
    const t = [...radiosTienda].find(r => r.checked)?.value;
    caja.querySelectorAll('optgroup').forEach(g => { g.hidden = !!t && g.dataset.tienda !== t; g.disabled = g.hidden; });
    const actual = caja.selectedOptions[0];
    if (!actual || actual.parentElement.disabled) {
      const primera = caja.querySelector(`optgroup[data-tienda="${t}"] option`);
      if (primera) primera.selected = true;
    }
  };
  radiosTienda.forEach(r => r.addEventListener('change', filtrar));
  filtrar();
}

// 16. Compras: importe de cada fila y total de la compra
const fCompra = document.getElementById('fCompra');
if (fCompra) {
  const calcular = () => {
    let total = 0;
    fCompra.querySelectorAll('.lineas-compra tbody tr').forEach(tr => {
      const c = parseFloat(tr.querySelector('[name^="cantidad"]').value.replace(',', '.'));
      const u = parseFloat(tr.querySelector('[name^="costo"]').value.replace(',', '.'));
      const ok = c > 0 && u >= 0;
      const imp = ok ? Math.round(c * u * 100) / 100 : 0;
      total += imp;
      tr.querySelector('.importe').textContent = ok ? 'S/ ' + imp.toFixed(2) : '—';
    });
    document.getElementById('totalCompra').textContent = 'S/ ' + total.toFixed(2);
  };
  fCompra.addEventListener('input', calcular);
  calcular();
}

// 17. Reportes: las fechas "desde / hasta" solo se muestran al elegir "Elegir fechas"
const periodo = document.getElementById('periodo');
if (periodo) {
  const verRango = () => document.querySelectorAll('.filtros-reporte .rango').forEach(l => { l.hidden = periodo.value !== 'rango'; });
  periodo.addEventListener('change', verRango);
  verRango();
}
