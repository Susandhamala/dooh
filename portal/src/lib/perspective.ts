/**
 * A CSS matrix3d that maps a w×h box onto any four corners, so a flat ad can be laid onto
 * a screen in a photo. Corners go clockwise from the top left. Use with `transform-origin: 0 0`.
 */
type M3 = number[]

const adj = (m: M3): M3 => [
  m[4]! * m[8]! - m[5]! * m[7]!, m[2]! * m[7]! - m[1]! * m[8]!, m[1]! * m[5]! - m[2]! * m[4]!,
  m[5]! * m[6]! - m[3]! * m[8]!, m[0]! * m[8]! - m[2]! * m[6]!, m[2]! * m[3]! - m[0]! * m[5]!,
  m[3]! * m[7]! - m[4]! * m[6]!, m[1]! * m[6]! - m[0]! * m[7]!, m[0]! * m[4]! - m[1]! * m[3]!,
]

function mul(a: M3, b: M3): M3 {
  const c: M3 = []
  for (let i = 0; i < 3; i++) for (let j = 0; j < 3; j++) {
    let s = 0
    for (let k = 0; k < 3; k++) s += a[3 * i + k]! * b[3 * k + j]!
    c[3 * i + j] = s
  }
  return c
}

const mulV = (m: M3, v: number[]) => [0, 1, 2].map((i) => m[3 * i]! * v[0]! + m[3 * i + 1]! * v[1]! + m[3 * i + 2]! * v[2]!)

/** The matrix taking the unit basis to these four points. */
function basis(p: [number, number][]): M3 {
  const m = [p[0]![0], p[1]![0], p[2]![0], p[0]![1], p[1]![1], p[2]![1], 1, 1, 1]
  const v = mulV(adj(m), [p[3]![0], p[3]![1], 1])
  return mul(m, [v[0]!, 0, 0, 0, v[1]!, 0, 0, 0, v[2]!])
}

export function quadTransform(w: number, h: number, to: [number, number][]): string {
  // Source corners clockwise from top left; basis() wants them as TL, TR, BL, BR.
  const src: [number, number][] = [[0, 0], [w, 0], [0, h], [w, h]]
  const dst: [number, number][] = [to[0]!, to[1]!, to[3]!, to[2]!]
  const t = mul(basis(dst), adj(basis(src)))
  const n = t.map((x) => x / t[8]!)
  return `matrix3d(${[n[0], n[3], 0, n[6], n[1], n[4], 0, n[7], 0, 0, 1, 0, n[2], n[5], 0, n[8]].join(',')})`
}
