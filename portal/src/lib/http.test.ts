import { AxiosError, AxiosHeaders } from 'axios'
import { describe, expect, it, vi } from 'vitest'
import { fieldErrors, http, mfaNeeded, setToken, setUnauthorizedHandler } from './http'

type Handler = { fulfilled: (c: any) => any; rejected: (e: any) => any }
const handlers = (i: unknown) => (i as { handlers: Handler[] }).handlers

function apiError(status: number, data: unknown): AxiosError {
  const config = { headers: new AxiosHeaders() }
  return new AxiosError('x', 'ERR', config as any, null, { status, data, statusText: '', headers: {}, config } as any)
}

describe('http client', () => {
  it('attaches the bearer token only when set', () => {
    const req = handlers(http.interceptors.request)[0]
    setToken('tok')
    expect(req.fulfilled({ headers: {} }).headers.Authorization).toBe('Bearer tok')
    setToken(null)
    expect(req.fulfilled({ headers: {} }).headers.Authorization).toBeUndefined()
  })

  it('drops the session on 401', async () => {
    const onLost = vi.fn()
    setUnauthorizedHandler(onLost)
    setToken('tok')
    await expect(handlers(http.interceptors.response)[0].rejected(apiError(401, {}))).rejects.toBeTruthy()
    expect(onLost).toHaveBeenCalledOnce()
  })

  it('reads field errors and the mfa hint from the envelope', () => {
    expect(fieldErrors(apiError(422, { errors: { email: ['Taken.', 'Other'] } }))).toEqual({ email: 'Taken.' })
    expect(mfaNeeded(apiError(403, { errors: { mfa: ['verify'] } }))).toBe('verify')
    expect(mfaNeeded(apiError(403, { errors: null }))).toBeNull()
  })
})
