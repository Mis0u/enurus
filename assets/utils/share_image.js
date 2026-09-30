// Capture d'un nœud hors-écran en image PNG (html-to-image) et partage natif du fichier —
// partagé par la carte de séance et les écrans du résumé annuel.
import { toBlob } from 'html-to-image';

const PNG = 'image/png';

// Le nœud est caché hors-écran (position fixed + énorme offset négatif) : html-to-image garde son
// style inline tel quel, l'offset serait appliqué à l'intérieur de l'image et la viderait.
const ON_SCREEN_STYLE = { position: 'static', left: '0', top: '0' };

export async function captureToBlob(node, pixelRatio) {
    const blob = await toBlob(node, { pixelRatio, style: ON_SCREEN_STYLE });

    if (!blob) {
        throw new Error('Image capture returned no data.');
    }

    return blob;
}

// Firefox mobile et la plupart des navigateurs desktop ne partagent que du texte ou une URL,
// jamais un fichier.
export function canShareImageFiles() {
    const probe = new File([], 'probe.png', { type: PNG });

    return Boolean(navigator.canShare?.({ files: [probe] }));
}

export async function shareImage(blob, fileName) {
    await navigator.share({ files: [new File([blob], fileName, { type: PNG })] });
}
