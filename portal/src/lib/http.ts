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

export function setToken(value: string | null): void {
  token = value
}

http.interceptors.request.use((config) => {
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

export async function get<T>(url: string, params?: Record<string, unknown>): Promise<Envelope<T>> {
  const res = await http.get<Envelope<T>>(url, { params })
  return res.data
}

export async function post<T>(url: string, body?: unknown): Promise<Envelope<T>> {
  const res = await http.post<Envelope<T>>(url, body)
  return res.data
}

export function errorMessage(err: unknown): string {
  const e = err as AxiosError<Envelope<unknown>>
  return e.response?.data?.message ?? e.message ?? 'Something went wrong'
}
