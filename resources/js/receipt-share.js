import html2canvas from 'html2canvas';

window.bagikanStruk = async function () {
    const el = document.querySelector('.receipt');
    if (!el) {
        console.error('Elemen .receipt tidak ditemukan');
        return;
    }

    const canvas = await html2canvas(el, {
        scale: 3,
        backgroundColor: '#ffffff',
        useCORS: true,
    });

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
    const file = new File([blob], 'struk-receipt.png', { type: 'image/png' });

    if (navigator.canShare && navigator.canShare({ files: [file] })) {
        await navigator.share({ files: [file], title: 'Cetak struk' });
    } else {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'struk-receipt.png';
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }
};
