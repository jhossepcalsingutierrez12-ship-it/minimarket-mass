// Minimarket Mass · JavaScript del sistema. Corre en el navegador y solo mejora la pantalla:
// los datos y la seguridad los maneja PHP en el servidor.

// 1. Catálogo: buscar y filtrar sin recargar la página
const grid = document.getElementById('grid');
if (grid) {
  const buscar = document.getElementById('buscar');
  const estado = document.getElementById('filtroEstado');
  const chips = document.querySelectorAll('#chips .chip');
  const vacio = document.getElementById('vacio');
  let categoria = '';

  // Los filtros solo sirven con JavaScript, por eso empiezan ocultos
  document.querySelectorAll('.solo-js').forEach(el => { el.hidden = false; });

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
const aviso = document.querySelector('[data-espera]');
if (aviso) {
  let s = parseInt(aviso.dataset.espera, 10);
  const reloj = setInterval(() => {
    s--;
    aviso.textContent = 'Demasiados intentos. Espera ' + s + ' segundos y vuelve a intentar.';
    if (s <= 0) { clearInterval(reloj); location.href = '?accion=login'; }
  }, 1000);
}
