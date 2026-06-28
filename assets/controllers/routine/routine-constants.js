// assets/controllers/routine/routine-constants.js
//
// Constantes partagées entre les controllers create et edit.

export const CSS = {
    exercise: {
        idle:  'border-white/[0.07]',
        added: 'border-green-500/30 bg-green-500/[0.04]',
    },
    btn: {
        base:  'w-7 h-7 rounded-[7px] border flex items-center justify-center flex-shrink-0 cursor-pointer transition-all duration-200',
        idle:  'border-white/[0.07] bg-white/[0.04] text-[#8b9bb4] hover:bg-rose-500/[0.15] hover:border-rose-500/40 hover:text-rose-500 hover:scale-[1.06]',
        added: 'border-green-500/35 bg-green-500/[0.12] text-green-400 cursor-default',
    },
};
/* eslint-disable quotes */
export const SVG = {
    plus:  `<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>`,
    check: `<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>`,
    drag:  `<svg class="w-3.5 h-5" viewBox="0 0 14 20" fill="currentColor"><circle cx="4" cy="4" r="1.5"/><circle cx="10" cy="4" r="1.5"/><circle cx="4" cy="10" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="4" cy="16" r="1.5"/><circle cx="10" cy="16" r="1.5"/></svg>`,
    cross: `<svg class="w-[13px] h-[13px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>`,
    warn:  `<svg class="w-3.5 h-3.5 text-rose-500 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>`,
};
