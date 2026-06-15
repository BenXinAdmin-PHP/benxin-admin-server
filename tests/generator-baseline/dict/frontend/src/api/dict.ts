/*
 * +----------------------------------------------------------------------
 * | @project   BenXinAdmin
 * | @mission   字典类型接口（CRUD + 状态 — /admin/v1/dicts）
 * | @author    仗键天涯(daxing)
 * | @email     3442535897@qq.com
 * | @date      2026-06-12
 * +----------------------------------------------------------------------
 */
import { request, type ApiEnvelope, type PageResult } from '@/utils/request'

/** 字典类型行（列表/详情共用） */
export interface DictItem {
  id: number
  name: string
  type: string
  status: number
  remark: string
  created_at: string | null
  updated_at: string | null
}

/** GET /admin/v1/dicts —— 分页列表（status 精确） */
export function listDicts(
  params: Record<string, unknown>,
): Promise<ApiEnvelope<PageResult<DictItem>>> {
  return request<PageResult<DictItem>>({ url: '/v1/dicts', method: 'get', params })
}

/** GET /admin/v1/dicts/:id —— 详情 */
export function getDict(id: number): Promise<ApiEnvelope<DictItem>> {
  return request<DictItem>({ url: `/v1/dicts/${id}`, method: 'get' })
}

/** POST /admin/v1/dicts —— 新增 */
export function createDict(data: Record<string, unknown>): Promise<ApiEnvelope<DictItem>> {
  return request<DictItem>({ url: '/v1/dicts', method: 'post', data })
}

/** PUT /admin/v1/dicts/:id —— 更新（选择性字段） */
export function updateDict(
  id: number,
  data: Record<string, unknown>,
): Promise<ApiEnvelope<DictItem>> {
  return request<DictItem>({ url: `/v1/dicts/${id}`, method: 'put', data })
}

/** DELETE /admin/v1/dicts/:id —— 删除 */
export function deleteDict(id: number): Promise<ApiEnvelope<null>> {
  return request<null>({ url: `/v1/dicts/${id}`, method: 'delete' })
}

/** PUT /admin/v1/dicts/:id/status —— 启停 */
export function setDictStatus(id: number, status: number): Promise<ApiEnvelope<DictItem>> {
  return request<DictItem>({ url: `/v1/dicts/${id}/status`, method: 'put', data: { status } })
}
