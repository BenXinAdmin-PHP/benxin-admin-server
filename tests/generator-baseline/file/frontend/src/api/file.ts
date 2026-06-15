/*
 * +----------------------------------------------------------------------
 * | @project   BenXinAdmin
 * | @mission   文件接口（CRUD — /admin/v1/files）
 * | @author    仗键天涯(daxing)
 * | @email     3442535897@qq.com
 * | @date      2026-06-12
 * +----------------------------------------------------------------------
 */
import { request, type ApiEnvelope, type PageResult } from '@/utils/request'

/** 文件行（列表/详情共用） */
export interface FileItem {
  id: number
  tenant_id: number
  create_by: number
  create_dept: number
  original_name: string
  file_name: string
  path: string
  mime: string
  ext: string
  size: number
  storage: string
  hash: string
  url: string
  created_at: string | null
  updated_at: string | null
}

/** GET /admin/v1/files —— 分页列表 */
export function listFiles(
  params: Record<string, unknown>,
): Promise<ApiEnvelope<PageResult<FileItem>>> {
  return request<PageResult<FileItem>>({ url: '/v1/files', method: 'get', params })
}

/** GET /admin/v1/files/:id —— 详情 */
export function getFile(id: number): Promise<ApiEnvelope<FileItem>> {
  return request<FileItem>({ url: `/v1/files/${id}`, method: 'get' })
}

/** POST /admin/v1/files —— 新增 */
export function createFile(data: Record<string, unknown>): Promise<ApiEnvelope<FileItem>> {
  return request<FileItem>({ url: '/v1/files', method: 'post', data })
}

/** PUT /admin/v1/files/:id —— 更新（选择性字段） */
export function updateFile(
  id: number,
  data: Record<string, unknown>,
): Promise<ApiEnvelope<FileItem>> {
  return request<FileItem>({ url: `/v1/files/${id}`, method: 'put', data })
}

/** DELETE /admin/v1/files/:id —— 删除 */
export function deleteFile(id: number): Promise<ApiEnvelope<null>> {
  return request<null>({ url: `/v1/files/${id}`, method: 'delete' })
}
