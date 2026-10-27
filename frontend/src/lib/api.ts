import axios, { type InternalAxiosRequestConfig } from 'axios'

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errors: Record<string, string[]> = {},
    public retryAfter: number | null = null,
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

export function toApiError(error: unknown): ApiError {
  if (error instanceof ApiError) return error
  if (!axios.isAxiosError(error)) return new ApiError(0, 'Có lỗi xảy ra. Vui lòng thử lại.')
  const status = error.response?.status ?? 0
  const data: unknown = error.response?.data
  const payload = data && typeof data === 'object' ? data as Record<string, unknown> : {}
  const errors: Record<string, string[]> = {}
  if (payload.errors && typeof payload.errors === 'object') {
    for (const [field, messages] of Object.entries(payload.errors)) {
      if (Array.isArray(messages)) errors[field] = messages.filter((message): message is string => typeof message === 'string')
    }
  }
  const messages: Record<number, string> = {
    0: 'Không kết nối được với máy chủ. Vui lòng kiểm tra kết nối và thử lại.',
    401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
    403: 'Bạn không có quyền thực hiện thao tác này.',
    404: 'Không tìm thấy nội dung bạn yêu cầu.',
    409: 'Dữ liệu đã thay đổi. Vui lòng kiểm tra lại trước khi tiếp tục.',
    419: 'Phiên bảo mật đã thay đổi. Vui lòng thử lại thao tác.',
    422: 'Vui lòng kiểm tra lại thông tin đã nhập.',
    429: 'Bạn đã thử quá nhiều lần. Vui lòng chờ một lúc rồi thử lại.',
  }
  const message = status >= 400 && status < 500 && typeof payload.message === 'string'
    ? payload.message : messages[status] ?? 'Máy chủ chưa thể xử lý yêu cầu. Vui lòng thử lại sau.'
  const retryHeader = error.response?.headers['retry-after']
  const retrySeconds = Number(retryHeader)
  const retryAfter = retryHeader != null && Number.isFinite(retrySeconds) && retrySeconds >= 0
    ? Math.ceil(retrySeconds) : null
  return new ApiError(status, message, errors, retryAfter)
}

export const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  withXSRFToken: true,
  timeout: 15_000,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
})

type SessionListener = () => void
const sessionListeners = new Set<SessionListener>()
type SessionRequest = InternalAxiosRequestConfig & { sessionRevision?: number }
let sessionRevision = 0

export function advanceSessionRevision() {
  sessionRevision++
}

api.interceptors.request.use(config => {
  const request = config as SessionRequest
  request.sessionRevision = sessionRevision
  return config
})

export function onSessionExpired(listener: SessionListener) {
  sessionListeners.add(listener)
  return () => sessionListeners.delete(listener)
}

api.interceptors.response.use(response => response, error => {
  const normalized = toApiError(error)
  const request = error.config as SessionRequest | undefined
  if (normalized.status === 401 && request?.url !== '/auth/login' && request?.sessionRevision === sessionRevision) {
    for (const listener of sessionListeners) listener()
  }
  return Promise.reject(normalized)
})

// Refresh CSRF before explicit user actions; never replay a POST automatically.
export async function prepareCsrf(): Promise<void> {
  await api.get('/auth/csrf-cookie')
}
