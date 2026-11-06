// Minimarket Mass · JavaScript del sistema. Corre en el navegador y solo mejora la pantalla:
// los datos y la seguridad los maneja PHP en el servidor. Cada parte revisa si su
// elemento existe en la página antes de hacer algo.
'use strict';

const $ = sel => document.querySelector(sel);
const $$ = sel => document.querySelectorAll(sel);

// 0. En todas las páginas: avisos flotantes (toast) que se van solos y menú hamburguesa en el celular
function activarToast(toast, espera) {
  const cerrar = () => { toast.classList.add('saliendo'); setTimeout(() => toast.closest('.toast-zona')?.remove(), 200); };
  let tiempo = setTimeout(cerrar, espera || (toast.classList.contains('err') ? 8000 : 5000));
  toast.addEventListener('mouseenter', () => clearTimeout(tiempo));
  toast.addEventListener('mouseleave', () => { tiempo = setTimeout(cerrar, 2500); });
  toast.querySelector('.toast-x')?.addEventListener('click', cerrar);
}
$$('.toast').forEach(t => activarToast(t));

// 0b. Administrador: cada 30 s pregunta si cerraron una caja con faltante de dinero.
// Si hay uno nuevo, la campana suma y sale un aviso flotante (sin recargar la página).
if (document.body.dataset.faltantes !== undefined) {
  const campana = document.getElementById('campanaNoti');
  const numero = document.getElementById('notiN');
  let conocidos = parseInt(document.body.dataset.faltantes, 10) || 0;
  const revisar = async () => {
    try {
      const r = await fetch('?accion=faltantes_n', { headers: { Accept: 'application/json' }, cache: 'no-store' });
      if (!r.ok) return;
      const d = await r.json();
      if (d.n > conocidos && d.texto) {
        const zona = document.createElement('div');
        zona.className = 'toast-zona';
        zona.innerHTML = '<div class="toast err toast-faltante" role="alert">' + campana.querySelector('svg').outerHTML
          + '<span><b>¡Faltó dinero al cerrar una caja!</b><br></span>'
          + '<a class="btn sm" href="?accion=faltantes">Revisar</a>'
          + '<button type="button" class="toast-x" aria-label="Cerrar aviso"><svg class="ico" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg></button></div>';
        zona.querySelector('span').append(d.texto);   // como texto, nunca como HTML
        document.body.appendChild(zona);
        activarToast(zona.querySelector('.toast'), 15000);
      }
      if (d.n !== conocidos) {
        conocidos = d.n;
        numero.textContent = d.n;
        numero.hidden = d.n === 0;
        campana.classList.toggle('hay', d.n > 0);
        campana.setAttribute('aria-label', d.n ? d.n + ' faltante(s) de caja sin revisar' : 'Faltantes de caja: nada nuevo');
      }
    } catch (e) { /* sin conexión: se vuelve a intentar en 30 s */ }
  };
  setInterval(revisar, 30000);
}
const btnMenu = document.getElementById('btnMenu');
if (btnMenu) {
  const menu = document.getElementById('menuLateral');
  const poner = abierto => {
    menu.classList.toggle('abierto', abierto);
    btnMenu.setAttribute('aria-expanded', String(abierto));
    btnMenu.setAttribute('aria-label', abierto ? 'Cerrar menú' : 'Abrir menú');
  };
  btnMenu.addEventListener('click', () => poner(!menu.classList.contains('abierto')));
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && menu.classList.contains('abierto')) { poner(false); btnMenu.focus(); } });
}

// 1. Catálogo: buscar, filtrar por estado y categoría, y ordenar sin recargar la página
const grid = document.getElementById('grid');
if (grid) {
  const buscar = document.getElementById('buscar');
  const estado = document.getElementById('filtroEstado');
  const ordenar = document.getElementById('ordenar');
  const chips = $$('#chips .chip');
  const vacio = document.getElementById('vacio');
  const resultado = document.getElementById('resultado');
  const tarjetas = [...grid.querySelectorAll('.prod')];
  let categoria = '';

  // Los filtros solo sirven con JavaScript, por eso empiezan ocultos
  $$('.solo-js').forEach(el => { el.hidden = false; });

  function filtrar() {
    const q = buscar.value.trim().toLowerCase();
    let visibles = 0;
    tarjetas.forEach(t => {
      const ok = (!categoria || t.dataset.cat === categoria)
              && (!estado.value || t.dataset.estado === estado.value)
              && (!q || t.dataset.nombre.includes(q) || t.dataset.sku.includes(q));
      t.hidden = !ok;
      if (ok) visibles++;
    });
    vacio.hidden = visibles > 0;
    const filtrado = categoria || estado.value || q;
    resultado.hidden = !filtrado;
    resultado.textContent = filtrado ? `Mostrando ${visibles} de ${tarjetas.length} productos.` : '';
  }

  function ordenarTarjetas() {
    const [campo, sentido] = ordenar.value.split('-');
    const signo = sentido === 'desc' ? -1 : 1;
    tarjetas.slice().sort((a, b) => campo === 'nombre'
      ? a.dataset.nombre.localeCompare(b.dataset.nombre, 'es')
      : signo * (parseFloat(a.dataset[campo]) - parseFloat(b.dataset[campo])) || a.dataset.nombre.localeCompare(b.dataset.nombre, 'es'))
      .forEach(t => grid.appendChild(t));
  }

  chips.forEach(chip => chip.addEventListener('click', () => {
    categoria = chip.dataset.cat;
    chips.forEach(c => { c.classList.toggle('active', c === chip); c.setAttribute('aria-pressed', String(c === chip)); });
    filtrar();
  }));
  buscar.addEventListener('input', filtrar);
  estado.addEventListener('change', filtrar);
  ordenar.addEventListener('change', ordenarTarjetas);
  document.getElementById('limpiarFiltros')?.addEventListener('click', () => {
    buscar.value = ''; estado.value = ''; categoria = '';
    chips.forEach((c, i) => { c.classList.toggle('active', i === 0); c.setAttribute('aria-pressed', String(i === 0)); });
    filtrar(); buscar.focus();
  });
  ordenarTarjetas();
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

// 15. Abrir caja al entrar: al elegir la tienda solo se ven sus 3 cajas (y se marca la primera libre)
const radiosTienda = document.querySelectorAll('#fInicioCaja input[name="tienda"]');
if (radiosTienda.length) {
  const filtrar = () => {
    const t = [...radiosTienda].find(r => r.checked)?.value;
    const cards = document.querySelectorAll('#fInicioCaja .opcion-caja');
    cards.forEach(c => { c.hidden = !!t && c.dataset.tienda !== t; });
    const marcada = document.querySelector('#fInicioCaja input[name="caja"]:checked');
    if (!marcada || marcada.closest('.opcion-caja').hidden) {
      if (marcada) marcada.checked = false;
      const libre = [...cards].find(c => !c.hidden && !c.querySelector('input').disabled);
      if (libre) libre.querySelector('input').checked = true;
    }
  };
  radiosTienda.forEach(r => r.addEventListener('change', filtrar));
  filtrar();
}

// 18. Agregar producto: al elegir un producto nuevo de la lista, el formulario se llena solo
const sugerido = document.getElementById('sugerido');
if (sugerido) {
  sugerido.addEventListener('change', () => {
    const o = sugerido.selectedOptions[0];
    if (!o || !o.value) return;
    const poner = (id, v) => { const el = document.getElementById(id); if (el && v !== undefined) { el.value = v; el.dispatchEvent(new Event('input', {bubbles: true})); } };
    poner('sku', o.value); poner('nombre', o.dataset.nombre); poner('precio', o.dataset.precio); poner('contenido', o.dataset.contenido);
    poner('perecible', o.dataset.perecible); poner('stock', o.dataset.stock); poner('minimo', o.dataset.minimo);
    poner('categoria', o.dataset.categoria); poner('marca', o.dataset.marca);
    const prev = document.getElementById('fotoPrev');
    if (prev) prev.innerHTML = o.dataset.foto ? `<img src="${o.dataset.foto}" alt=""><span>Foto lista del producto nuevo.</span>` : '<span>Opcional. Sin foto se muestra el ícono de la categoría.</span>';
  });
}
