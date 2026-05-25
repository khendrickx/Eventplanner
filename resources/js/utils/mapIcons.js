// resources/js/utils/mapIcons.js
//
// Registers all element type icons with a MapLibre map instance.
// Call loadElementIcons(map) once after the map style loads.
// Icons are derived from elementTypes.js + elementIcons.js — the only
// place to change an icon is those two files.

import { elementTypes } from '@/config/elementTypes.js'
import { iconImage } from '@/config/elementIcons.js'

/**
 * Load all element type icons into a MapLibre map instance.
 * Must be called after map.on('load', ...) fires.
 */
export async function loadElementIcons(map) {
    await Promise.all(
        elementTypes
            .filter(t => t.type !== 'group' && t.icon)
            .map(async (t) => {
                if (map.hasImage(t.id)) return
                try {
                    const img = await iconImage(t.icon, t.defaultStyle?.color || '#3b82f6', 48)
                    map.addImage(t.id, img, { pixelRatio: 2 })
                } catch (e) {
                    console.warn(`[mapIcons] Failed to load icon for ${t.id}:`, e)
                }
            })
    )
}
