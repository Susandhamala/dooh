import { describe, expect, it } from 'vitest'
import { quadTransform } from './perspective'

/** Apply a CSS matrix3d (column-major) to a 2D point. */
function apply(css: string, x: number, y: number): [number, number] {
  const m = css.slice(9, -1).split(',').map(Number)
  const X = m[0]! * x + m[4]! * y + m[12]!
  const Y = m[1]! * x + m[5]! * y + m[13]!
  const W = m[3]! * x + m[7]! * y + m[15]!
  return [X / W, Y / W]
}

describe('quadTransform', () => {
  const to: [number, number][] = [[12, 20], [610, 8], [640, 330], [5, 300]]
  const t = quadTransform(1000, 500, to)

  it('sends each corner of the box to the matching corner of the quad', () => {
    for (const [[x, y], [ex, ey]] of [[[0, 0], to[0]], [[1000, 0], to[1]], [[1000, 500], to[2]], [[0, 500], to[3]]] as const) {
      const [px, py] = apply(t, x, y)
      expect(px).toBeCloseTo(ex!, 6)
      expect(py).toBeCloseTo(ey!, 6)
    }
  })

  it('is the identity when the quad is the box itself', () => {
    const id = quadTransform(100, 50, [[0, 0], [100, 0], [100, 50], [0, 50]])
    expect(apply(id, 37, 21)).toEqual([expect.closeTo(37, 9), expect.closeTo(21, 9)])
  })
})
