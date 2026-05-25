import { describe, it, expect } from 'vitest'
import { computeBoundsFromElements } from './mapBounds.js'

describe('computeBoundsFromElements', () => {
    it('returns null for empty elements', () => {
        expect(computeBoundsFromElements([])).toBeNull()
    })

    it('returns null for elements with no geometry', () => {
        expect(computeBoundsFromElements([{ geometry: null }])).toBeNull()
    })

    it('handles a single Point', () => {
        const elements = [
            { geometry: { type: 'Point', coordinates: [10, 20] } },
        ]
        const bounds = computeBoundsFromElements(elements)
        expect(bounds).not.toBeNull()
        expect(bounds[0][0]).toBe(10) // minLng
        expect(bounds[0][1]).toBe(20) // minLat
        expect(bounds[1][0]).toBe(10) // maxLng
        expect(bounds[1][1]).toBe(20) // maxLat
    })

    it('handles multiple Points', () => {
        const elements = [
            { geometry: { type: 'Point', coordinates: [4.3, 50.8] } },
            { geometry: { type: 'Point', coordinates: [4.5, 51.0] } },
        ]
        const bounds = computeBoundsFromElements(elements)
        expect(bounds[0]).toEqual([4.3, 50.8]) // SW
        expect(bounds[1]).toEqual([4.5, 51.0]) // NE
    })

    it('handles a LineString', () => {
        const elements = [
            {
                geometry: {
                    type: 'LineString',
                    coordinates: [[1, 2], [3, 4], [5, 0]],
                },
            },
        ]
        const bounds = computeBoundsFromElements(elements)
        expect(bounds[0]).toEqual([1, 0])
        expect(bounds[1]).toEqual([5, 4])
    })

    it('handles a Polygon', () => {
        const elements = [
            {
                geometry: {
                    type: 'Polygon',
                    coordinates: [[[0, 0], [10, 0], [10, 5], [0, 5], [0, 0]]],
                },
            },
        ]
        const bounds = computeBoundsFromElements(elements)
        expect(bounds[0]).toEqual([0, 0])
        expect(bounds[1]).toEqual([10, 5])
    })

    it('handles mixed geometry types', () => {
        const elements = [
            { geometry: { type: 'Point', coordinates: [1, 1] } },
            { geometry: { type: 'LineString', coordinates: [[-5, 3], [2, 8]] } },
            {
                geometry: {
                    type: 'Polygon',
                    coordinates: [[[0, 0], [4, 0], [4, 6], [0, 6], [0, 0]]],
                },
            },
        ]
        const bounds = computeBoundsFromElements(elements)
        expect(bounds[0][0]).toBe(-5)  // min lng
        expect(bounds[0][1]).toBe(0)   // min lat
        expect(bounds[1][0]).toBe(4)   // max lng
        expect(bounds[1][1]).toBe(8)   // max lat
    })

    it('skips elements with null geometry', () => {
        const elements = [
            { geometry: null },
            { geometry: { type: 'Point', coordinates: [7, 8] } },
        ]
        const bounds = computeBoundsFromElements(elements)
        expect(bounds).not.toBeNull()
        expect(bounds[0]).toEqual([7, 8])
    })
})
