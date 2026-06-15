/*
 * +----------------------------------------------------------------------
 * | @project   BenXinAdmin
 * | @mission   管理员接口（CRUD + 状态 — /admin/v1/admins）
 * | @author    仗键天涯(daxing)
 * | @email     3442535897@qq.com
 * | @date      2026-06-12
 * +----------------------------------------------------------------------
 */
import { request, type ApiEnvelope, type PageResult } from '@/utils/request'

/** 管理员行（列表/详情共用） */
export interface AdminItem {
  id: number
  username: string
  password: string
  nickname: string
  avatar: string
  mobile: string
  email: string
  dept_id: number
  status: number
  last_login_at: string | null
  last_login_ip: string
  remark: string
  created_at: string | null
  updated_at: string | null
}

/** GET /admin/v1/admins —— 分页列表（status 精确） */
export function listAdmins(
  params: Record<string, unknown>,
): Promise<ApiEnvelope<PageResult<AdminItem>>> {
  return request<PageResult<AdminItem>>({ url: '/v1/admins', method: 'get', params })
}

/** GET /admin/v1/admins/:id —— 详情 */
export function getAdmin(id: number): Promise<ApiEnvelope<AdminItem>> {
  return request<AdminItem>({ url: `/v1/admins/${id}`, method: 'get' })
}

/** POST /admin/v1/admins —— 新增 */
export function createAdmin(data: Record<string, unknown>): Promise<ApiEnvelope<AdminItem>> {
  return request<AdminItem>({ url: '/v1/admins', method: 'post', data })
}

/** PUT /admin/v1/admins/:id —— 更新（选择性字段） */
export function updateAdmin(
  id: number,
  data: Record<string, unknown>,
): Promise<ApiEnvelope<AdminItem>> {
  return request<AdminItem>({ url: `/v1/admins/${id}`, method: 'put', data })
}

/** DELETE /admin/v1/admins/:id —— 删除 */
export function deleteAdmin(id: number): Promise<ApiEnvelope<null>> {
  return request<null>({ url: `/v1/admins/${id}`, method: 'delete' })
}

/** PUT /admin/v1/admins/:id/status —— 启停 */
export function setAdminStatus(id: number, status: number): Promise<ApiEnvelope<AdminItem>> {
  return request<AdminItem>({ url: `/v1/admins/${id}/status`, method: 'put', data: { status } })
}
