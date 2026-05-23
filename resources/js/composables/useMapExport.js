export function useMapExport(getMap) {
    function exportPng({ print = false } = {}) {
        const map = getMap()
        if (!map) return

        const dpr = print ? 3 : 1
        const originalDpr = window.devicePixelRatio

        Object.defineProperty(window, 'devicePixelRatio', {
            get: () => dpr,
            configurable: true,
        })

        function restoreDpr() {
            Object.defineProperty(window, 'devicePixelRatio', {
                get: () => originalDpr,
                configurable: true,
            })
        }

        // Fallback: restore dpr after 3s if render event never fires
        const timeout = setTimeout(restoreDpr, 3000)

        map.once('render', () => {
            clearTimeout(timeout)
            const canvas = map.getCanvas()
            const url = canvas.toDataURL('image/png')

            const a = document.createElement('a')
            a.href = url
            a.download = print ? 'map-print.png' : 'map-digital.png'
            a.click()

            restoreDpr()
        })

        map.triggerRepaint()
    }

    return { exportPng }
}
