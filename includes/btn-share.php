
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

<script>
async function shareVoucher() {
  const voucher = document.getElementById('voucherCapture');
  if (!voucher) return;

  const codigoEl = voucher.querySelector('[data-codigo]');
  const codigo = codigoEl?.dataset.codigo || 'comprobante';

  if (typeof html2canvas === 'undefined') return;

  try {
    const canvas = await html2canvas(voucher, {
      scale: 2,
      backgroundColor: '#ffffff',
      useCORS: true,
      onclone: (doc) => {
        doc.querySelectorAll('#voucherCapture .cr-recibo-num').forEach((el) => {
          el.style.setProperty('display', 'inline-block', 'important');
          el.style.setProperty('box-sizing', 'border-box', 'important');
        });
        doc.querySelectorAll('#voucherCapture .cr-numero-chip--recibo').forEach((el) => {
          el.style.setProperty('display', 'table', 'important');
          el.style.setProperty('width', '72px', 'important');
          el.style.setProperty('height', '36px', 'important');
          el.style.setProperty('margin', '0', 'important');
          el.style.setProperty('padding', '0', 'important');
          el.style.setProperty('border', 'none', 'important');
          el.style.setProperty('background', 'transparent', 'important');
          el.style.setProperty('letter-spacing', '0', 'important');
          el.style.setProperty('table-layout', 'fixed', 'important');
          const cell = el.querySelector('td');
          if (cell) {
            cell.style.setProperty('display', 'block', 'important');
            cell.style.setProperty('width', '100%', 'important');
            cell.style.setProperty('height', '32px', 'important');
            cell.style.setProperty('line-height', '32px', 'important');
            cell.style.setProperty('text-align', 'center', 'important');
            cell.style.setProperty('vertical-align', 'middle', 'important');
            cell.style.setProperty('padding', '0 6px', 'important');
            cell.style.setProperty('box-sizing', 'border-box', 'important');
            cell.style.setProperty('font-family', 'Consolas, Monaco, "Courier New", monospace', 'important');
            cell.style.setProperty('letter-spacing', '0', 'important');
            if (el.classList.contains('cr-recibo-num--plain')) {
              cell.style.setProperty('background', 'transparent', 'important');
              cell.style.setProperty('border', 'none', 'important');
              cell.style.setProperty('border-radius', '0', 'important');
            } else if (el.classList.contains('cr-recibo-num--premiado')) {
              cell.style.setProperty('border-radius', '10px', 'important');
            }
          }
        });
      }
    });

    canvas.toBlob(async (blob) => {
      if (!blob) return;

      const fileName = `${codigo}.png`;
      const file = new File([blob], fileName, { type: 'image/png' });

      if (navigator.canShare && navigator.canShare({ files: [file] })) {
        await navigator.share({
          files: [file],
          title: 'Comprobante de venta',
          text: 'Aquí está tu comprobante'
        });
      } else {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
      }
    });
  } catch (err) {
    return;
  }
}
</script>
