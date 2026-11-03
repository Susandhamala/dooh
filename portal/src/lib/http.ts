import axios, { type AxiosError } from 'axios'

/** API response envelope shared with the Laravel backend. */
export interface Envelope<T> {
  success: boolean
  message: string
  data: T
  errors: Record<string, string[]> | null
  meta: Record<string, unknown> | null
}

export const http = axios.create({
  baseURL: '/api/v1',
  headers: { Accept: 'application/json' },
})

let token: string | null = null
let onUnauthorized: (() => void) | null = null

export function setToken(value: string | null): void {
  token = value
}

/** Called when the API says the session is gone (expired, revoked or suspended). */
export function setUnauthorizedHandler(handler: () => void): void {
  onUnauthorized = handler
}

http.interceptors.request.use((config) => {
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

http.interceptors.response.use(undefined, (err: AxiosError) => {
  if (err.response?.status === 401 && token) onUnauthorized?.()
  return Promise.reject(err)
})

export async function get<T>(url: string, params?: Record<string, unknown>): Promise<Envelope<T>> {
  return (await http.get<Envelope<T>>(url, { params })).data
}

export async function post<T>(url: string, body?: unknown): Promise<Envelope<T>> {
  return (await http.post<Envelope<T>>(url, body)).data
}

export async function patch<T>(url: string, body?: unknown): Promise<Envelope<T>> {
  return (await http.patch<Envelope<T>>(url, body)).data
}

export async function del<T>(url: string): Promise<Envelope<T>> {
  return (await http.delete<Envelope<T>>(url)).data
}

/** Multipart upload with progress (0–100). */
export async function upload<T>(url: string, form: FormData, onProgress?: (pct: number) => void): Promise<Envelope<T>> {
  return (
    await http.post<Envelope<T>>(url, form, {
      onUploadProgress: (e) => onProgress?.(e.total ? Math.round((e.loaded / e.total) * 100) : 0),
    })
  ).data
}

/** What a 403 says is missing before this action: accepted terms, or an identity document. */
export function gateNeeded(err: unknown): 'terms' | 'document' | null {
  const errors = (err as AxiosError<Envelope<unknown>>).response?.data?.errors
  if ((err as AxiosError).response?.status !== 403 || !errors) return null
  if (errors.terms) return 'terms'
  if (errors.document) return 'document'
  return null
}

export function errorMessage(err: unknown): string {
  const e = err as AxiosError<Envelope<unknown>>
  return e.response?.data?.message ?? e.message ?? 'Something went wrong'
}

/** Field errors from a 422 response, first message per field. */
export function fieldErrors(err: unknown): Record<string, string> {
  const errors = (err as AxiosError<Envelope<unknown>>).response?.data?.errors ?? {}
  return Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0] ?? '']))
}

/** True when the API rejected the token itself (expired, revoked, suspended), not a network or server hiccup. */
export function isUnauthorized(err: unknown): boolean {
  return (err as AxiosError).response?.status === 401
}

/** What a 403 says about two-factor: 'setup', 'verify' or null. */
export function mfaNeeded(err: unknown): 'setup' | 'verify' | null {
  const e = err as AxiosError<Envelope<unknown>>
  const mfa = e.response?.status === 403 ? e.response.data?.errors?.mfa?.[0] : undefined
  return mfa === 'setup' || mfa === 'verify' ? mfa : null
}
