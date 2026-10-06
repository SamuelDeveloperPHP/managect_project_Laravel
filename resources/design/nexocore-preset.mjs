// Gerado por build.mjs. Uso: import { presets } from './tailwind-preset.mjs'; presets.trilha

const tokens = {"$schema":"nexocore-design-tokens/1","description":"Fonte única dos tokens de design da família NexoCore. Os nomes dos tokens são iguais em todos os produtos; os valores mudam por tema (produto).","shared":{"status":{"success":{"50":"#e6f8f1","500":"#12b981","600":"#0e9c6d","700":"#0b7a55"},"warning":{"50":"#fff6e0","500":"#e5a100","600":"#b87a00","700":"#8a5a00"},"danger":{"50":"#fdecec","500":"#e5484d","600":"#c0343a","700":"#9a272c"},"info":{"50":"#e8f3fb","500":"#2f8fd1","600":"#1f72ab","700":"#175a88"}},"font":{"mono":"\"IBM Plex Mono\", ui-monospace, SFMono-Regular, Menlo, Consolas, monospace"},"focus":{"width":"2px","offset":"2px"},"motion":{"fast":"120ms","base":"200ms","slow":"320ms","easing":"cubic-bezier(0.2, 0, 0, 1)"},"zIndex":{"dropdown":30,"sticky":40,"overlay":50,"modal":60,"toast":70}},"themes":{"trilha":{"name":"Trilha+","intent":"Gestão de projetos: sóbrio, denso, legível por horas.","brand":{"50":"#eef5f5","100":"#d8e8e9","200":"#b3d0d3","300":"#86b1b6","400":"#4f8a91","500":"#2a6670","600":"#14505a","700":"#0f3d44","800":"#0b2e34","900":"#082126","950":"#05161a"},"neutral":{"50":"#f4f6f6","100":"#e9eded","200":"#d5dcdc","300":"#b7c2c2","400":"#8a9898","500":"#647474","600":"#4a5959","700":"#364343","800":"#222d2d","900":"#141d1e","950":"#0b1213"},"signal":{"300":"#e5b454","400":"#d89e2b","500":"#c98a12","600":"#a8700c"},"font":{"sans":"\"IBM Plex Sans\", system-ui, -apple-system, \"Segoe UI\", sans-serif","display":"\"IBM Plex Sans Condensed\", \"IBM Plex Sans\", system-ui, sans-serif"},"radius":{"sm":"3px","md":"4px","lg":"6px","xl":"8px"},"shadow":{"sm":"0 1px 0 rgba(20, 29, 30, 0.05)","md":"0 2px 6px rgba(20, 29, 30, 0.08)","lg":"0 8px 24px rgba(20, 29, 30, 0.12)"},"roles":{"action":"600","onTint":"700"}},"tide":{"name":"Tide ERP","intent":"PDV e fiscal: rápido, amigável, confiança no balcão.","brand":{"50":"#eef0ff","100":"#e0e3ff","200":"#c4c9ff","300":"#a3a9ff","400":"#8590ff","500":"#6571ff","600":"#4a53e6","700":"#3b43c9","800":"#2f36a3","900":"#272d80","950":"#181b4d"},"neutral":{"50":"#f6f7fc","100":"#f0f2fb","200":"#e7e9f4","300":"#cdd1e4","400":"#9a9fb8","500":"#6a708b","600":"#50567a","700":"#3a3f5c","800":"#272b48","900":"#1b1e35","950":"#12142a"},"signal":{"300":"#7ee2b8","400":"#3fcf9c","500":"#12b981","600":"#0e9c6d"},"font":{"sans":"\"Plus Jakarta Sans\", system-ui, -apple-system, \"Segoe UI\", sans-serif","display":"\"Sora\", \"Plus Jakarta Sans\", system-ui, sans-serif"},"radius":{"sm":"10px","md":"16px","lg":"24px","xl":"32px"},"shadow":{"sm":"0 1px 2px rgba(27, 30, 53, 0.06), 0 2px 6px rgba(27, 30, 53, 0.05)","md":"0 12px 30px -12px rgba(50, 56, 120, 0.30), 0 4px 12px -6px rgba(27, 30, 53, 0.12)","lg":"0 30px 60px -20px rgba(50, 56, 120, 0.40), 0 10px 24px -12px rgba(27, 30, 53, 0.16)"},"roles":{"action":"600","onTint":"700"}},"sgi":{"name":"SGI","intent":"Administrativo: formulários e tabelas, tema de painel clássico.","brand":{"50":"#e6f8f4","100":"#c9f0e5","200":"#94e2cc","300":"#5dcaa5","400":"#3cc4a8","500":"#1abb9c","600":"#169f85","700":"#128270","800":"#0f6e56","900":"#0a4e3f","950":"#052f26"},"neutral":{"50":"#f7f7f7","100":"#eef0f2","200":"#e6e9ed","300":"#c7ced6","400":"#9aa8b6","500":"#73879c","600":"#5a6b7d","700":"#3f5266","800":"#2a3f54","900":"#1d2d3c","950":"#111b25"},"signal":{"300":"#f5c76b","400":"#eeb03e","500":"#e39a12","600":"#b87a00"},"font":{"sans":"\"Segoe UI\", Roboto, Arial, sans-serif","display":"\"Segoe UI\", Roboto, Arial, sans-serif"},"radius":{"sm":"3px","md":"4px","lg":"6px","xl":"8px"},"shadow":{"sm":"0 1px 2px rgba(0, 0, 0, 0.05)","md":"0 4px 20px -2px rgba(0, 0, 0, 0.05)","lg":"0 8px 32px 0 rgba(0, 0, 0, 0.08)"},"roles":{"action":"700","onTint":"800","note":"Os degraus 500 e 600 do verde-água não alcançam contraste AA com texto branco (2,4:1 e 3,3:1); botões e links devem usar o degrau 700."}}}};
const themes = tokens.themes;
const shared = tokens.shared;
const presetFor = (id, { legacyAliases = false } = {}) => {
    const t = themes[id];
    const colors = {
        brand: t.brand, neutral: t.neutral, signal: t.signal,
        success: shared.status.success, warning: shared.status.warning, danger: shared.status.danger, info: shared.status.info,
    };
    if (legacyAliases) Object.assign(colors, { indigo: t.brand, blue: t.brand, slate: t.neutral, gray: t.neutral });

    return {
        theme: {
            extend: {
                colors,
                fontFamily: { sans: [t.font.sans], display: [t.font.display], mono: [shared.font.mono] },
                borderRadius: { md: t.radius.sm, lg: t.radius.md, xl: t.radius.lg, '2xl': t.radius.lg, '3xl': t.radius.xl },
                boxShadow: { sm: t.shadow.sm, md: t.shadow.md, lg: t.shadow.lg, xl: t.shadow.lg },
            },
        },
    };
};
const presets = Object.fromEntries(Object.keys(themes).map((id) => [id, presetFor(id)]));
const legacyPresets = Object.fromEntries(Object.keys(themes).map((id) => [id, presetFor(id, { legacyAliases: true })]));

export { tokens, presetFor, presets, legacyPresets };
export default presets;
