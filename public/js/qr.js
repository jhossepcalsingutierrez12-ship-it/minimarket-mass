// Minimarket Mass · generador de códigos QR sin internet (no usa librerías).
// Lo usa public/js/app.js para el QR de Yape/Plin y el QR de la boleta o factura.
'use strict';
/* ===================== GENERADOR DE QR (sin internet) =====================
   QR modelo 2, modo byte (UTF-8), corrección de errores nivel M, versiones 1 a 20.
   Basado en el algoritmo del estándar ISO/IEC 18004. */
window.QR = (() => {
  const ECC_M = [-1,10,16,26,18,24,16,18,22,22,26,30,22,22,24,24,28,28,26,26,26];
  const BLK_M = [-1,1,1,1,2,2,4,4,4,5,5,5,8,9,9,10,10,11,13,14,16];
  const bit = (x, i) => ((x >>> i) & 1) !== 0;

  function rawModules(v){
    let r = (16 * v + 128) * v + 64;
    if (v >= 2) { const n = Math.floor(v / 7) + 2; r -= (25 * n - 10) * n - 55; if (v >= 7) r -= 36; }
    return r;
  }
  const dataCodewords = v => Math.floor(rawModules(v) / 8) - ECC_M[v] * BLK_M[v];

  function gfMul(x, y){
    let z = 0;
    for (let i = 7; i >= 0; i--) { z = (z << 1) ^ ((z >>> 7) * 0x11D); z ^= ((y >>> i) & 1) * x; }
    return z;
  }
  function rsDivisor(deg){
    const r = new Array(deg).fill(0); r[deg - 1] = 1; let root = 1;
    for (let i = 0; i < deg; i++) {
      for (let j = 0; j < r.length; j++) { r[j] = gfMul(r[j], root); if (j + 1 < r.length) r[j] ^= r[j + 1]; }
      root = gfMul(root, 0x02);
    }
    return r;
  }
  function rsRemainder(data, div){
    const r = div.map(() => 0);
    for (const b of data) { const f = b ^ r.shift(); r.push(0); div.forEach((c, i) => r[i] ^= gfMul(c, f)); }
    return r;
  }
  function alignPos(v){
    if (v === 1) return [];
    const n = Math.floor(v / 7) + 2;
    const step = Math.ceil((v * 4 + 4) / (n * 2 - 2)) * 2;
    const r = [6];
    for (let p = v * 4 + 10; r.length < n; p -= step) r.splice(1, 0, p);
    return r;
  }

  function encode(text){
    const bytes = Array.from(new TextEncoder().encode(text));
    let v = 1;
    for (; v <= 20; v++) {
      const cc = v <= 9 ? 8 : 16;
      if (4 + cc + bytes.length * 8 <= dataCodewords(v) * 8) break;
    }
    if (v > 20) throw new Error('Texto muy largo para el QR');

    // Bits de datos
    const bb = [];
    const push = (val, len) => { for (let i = len - 1; i >= 0; i--) bb.push((val >>> i) & 1); };
    push(4, 4); push(bytes.length, v <= 9 ? 8 : 16); bytes.forEach(b => push(b, 8));
    const cap = dataCodewords(v) * 8;
    push(0, Math.min(4, cap - bb.length));
    push(0, (8 - bb.length % 8) % 8);
    for (let pad = 0xEC; bb.length < cap; pad ^= 0xEC ^ 0x11) push(pad, 8);
    const data = [];
    for (let i = 0; i < bb.length; i += 8) data.push(parseInt(bb.slice(i, i + 8).join(''), 2));

    // Bloques + corrección de errores + intercalado
    const nb = BLK_M[v], ecl = ECC_M[v], raw = Math.floor(rawModules(v) / 8);
    const nShort = nb - raw % nb, shortLen = Math.floor(raw / nb), div = rsDivisor(ecl);
    const blocks = [];
    for (let i = 0, k = 0; i < nb; i++) {
      const d = data.slice(k, k + shortLen - ecl + (i < nShort ? 0 : 1)); k += d.length;
      const e = rsRemainder(d, div); if (i < nShort) d.push(0); blocks.push(d.concat(e));
    }
    const cw = [];
    for (let i = 0; i < blocks[0].length; i++)
      blocks.forEach((b, j) => { if (i !== shortLen - ecl || j >= nShort) cw.push(b[i]); });

    // Matriz
    const size = v * 4 + 17;
    const m = Array.from({length: size}, () => new Array(size).fill(false));
    const fn = Array.from({length: size}, () => new Array(size).fill(false));
    const set = (x, y, d) => { m[y][x] = d; fn[y][x] = true; };

    for (let i = 0; i < size; i++) { set(6, i, i % 2 === 0); set(i, 6, i % 2 === 0); }
    [[3, 3], [size - 4, 3], [3, size - 4]].forEach(([cx, cy]) => {
      for (let dy = -4; dy <= 4; dy++) for (let dx = -4; dx <= 4; dx++) {
        const d = Math.max(Math.abs(dx), Math.abs(dy)), x = cx + dx, y = cy + dy;
        if (x >= 0 && x < size && y >= 0 && y < size) set(x, y, d !== 2 && d !== 4);
      }
    });
    const ap = alignPos(v), na = ap.length;
    for (let i = 0; i < na; i++) for (let j = 0; j < na; j++) {
      if ((i === 0 && j === 0) || (i === 0 && j === na - 1) || (i === na - 1 && j === 0)) continue;
      for (let dy = -2; dy <= 2; dy++) for (let dx = -2; dx <= 2; dx++)
        set(ap[i] + dx, ap[j] + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
    }
    const drawFormat = mask => {
      const d = (0 << 3) | mask; // nivel M = 00
      let r = d; for (let i = 0; i < 10; i++) r = (r << 1) ^ ((r >>> 9) * 0x537);
      const b = ((d << 10) | r) ^ 0x5412;
      for (let i = 0; i <= 5; i++) set(8, i, bit(b, i));
      set(8, 7, bit(b, 6)); set(8, 8, bit(b, 7)); set(7, 8, bit(b, 8));
      for (let i = 9; i < 15; i++) set(14 - i, 8, bit(b, i));
      for (let i = 0; i < 8; i++) set(size - 1 - i, 8, bit(b, i));
      for (let i = 8; i < 15; i++) set(8, size - 15 + i, bit(b, i));
      set(8, size - 8, true);
    };
    drawFormat(0);
    if (v >= 7) {
      let r = v; for (let i = 0; i < 12; i++) r = (r << 1) ^ ((r >>> 11) * 0x1F25);
      const b = (v << 12) | r;
      for (let i = 0; i < 18; i++) { const a = size - 11 + i % 3, c = Math.floor(i / 3); set(a, c, bit(b, i)); set(c, a, bit(b, i)); }
    }

    // Colocar los datos en zigzag
    let i = 0;
    for (let right = size - 1; right >= 1; right -= 2) {
      if (right === 6) right = 5;
      for (let vert = 0; vert < size; vert++) for (let j = 0; j < 2; j++) {
        const x = right - j, up = ((right + 1) & 2) === 0, y = up ? size - 1 - vert : vert;
        if (!fn[y][x] && i < cw.length * 8) { m[y][x] = bit(cw[i >>> 3], 7 - (i & 7)); i++; }
      }
    }

    // Máscaras: se elige la de menor penalidad
    const maskFn = [
      (x, y) => (x + y) % 2 === 0, (x, y) => y % 2 === 0, (x, y) => x % 3 === 0, (x, y) => (x + y) % 3 === 0,
      (x, y) => (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0, (x, y) => x * y % 2 + x * y % 3 === 0,
      (x, y) => (x * y % 2 + x * y % 3) % 2 === 0, (x, y) => ((x + y) % 2 + x * y % 3) % 2 === 0,
    ];
    const applyMask = k => { for (let y = 0; y < size; y++) for (let x = 0; x < size; x++) if (!fn[y][x] && maskFn[k](x, y)) m[y][x] = !m[y][x]; };
    const penalty = () => {
      let p = 0, dark = 0;
      for (let y = 0; y < size; y++) for (let dir = 0; dir < 2; dir++) {
        let run = 1;
        for (let x = 1; x < size; x++) {
          const a = dir ? m[x][y] : m[y][x], b = dir ? m[x - 1][y] : m[y][x - 1];
          if (a === b) { run++; if (run === 5) p += 3; else if (run > 5) p++; } else run = 1;
        }
      }
      for (let y = 0; y < size - 1; y++) for (let x = 0; x < size - 1; x++) {
        const c = m[y][x]; if (c === m[y][x + 1] && c === m[y + 1][x] && c === m[y + 1][x + 1]) p += 3;
      }
      m.forEach(r => r.forEach(c => dark += c));
      return p + Math.floor(Math.abs(dark * 20 - size * size * 10) / (size * size)) * 10;
    };
    let best = 0, bestP = Infinity;
    for (let k = 0; k < 8; k++) { applyMask(k); drawFormat(k); const p = penalty(); if (p < bestP) { bestP = p; best = k; } applyMask(k); }
    applyMask(best); drawFormat(best);
    return m;
  }

  // Devuelve un <svg> con borde blanco de 4 módulos
  function svg(text, px = 200, color = '#000'){
    const m = encode(text), n = m.length, q = 4, t = n + q * 2;
    let d = '';
    m.forEach((r, y) => r.forEach((c, x) => { if (c) d += `M${x + q},${y + q}h1v1h-1z`; }));
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${t} ${t}" width="${px}" height="${px}" shape-rendering="crispEdges"><rect width="${t}" height="${t}" fill="#fff"/><path d="${d}" fill="${color}"/></svg>`;
  }
  // Dibuja el QR en un canvas (para el comprobante descargable)
  function draw(ctx, text, x0, y0, px){
    const m = encode(text), n = m.length, s = px / n;
    ctx.fillStyle = '#fff'; ctx.fillRect(x0, y0, px, px); ctx.fillStyle = '#000';
    m.forEach((r, y) => r.forEach((c, x) => { if (c) ctx.fillRect(x0 + x * s, y0 + y * s, Math.ceil(s), Math.ceil(s)); }));
  }
  return {encode, svg, draw};
})();


