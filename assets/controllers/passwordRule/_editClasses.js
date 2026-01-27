/**
 * @param rule {boolean}
 * @param target {HTMLElement}
 */
export function editClasses(rule, target) {
    const svg = target.querySelector('svg');
    if (rule) {
        target.classList.add('text-green-400');
        target.classList.remove('text-slate-400');
        svg.classList.add('text-green-500');
        svg.classList.remove('text-slate-500');
    } else {
        target.classList.remove('text-green-400');
        target.classList.add('text-slate-400');
        svg.classList.remove('text-green-500');
        svg.classList.add('text-slate-500');
    }
}
