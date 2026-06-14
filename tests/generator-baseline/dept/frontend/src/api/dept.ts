/*
 * +----------------------------------------------------------------------
 * | @project   BenXinAdmin
 * | @mission   部门接口（树 + CRUD + 状态 — /admin/v1/depts）
 * | @author    仗键天涯(daxing)
 * | @email     3442535897@qq.com
 * | @date      2026-06-12
 * +----------------------------------------------------------------------
 */
import { request, type ApiEnvelope } from '@/utils/request'

/** 部门树节点（管理端全字段；children 仅在有子节点时存在） */
export interface DeptItem {
  id: number
  tenant_id: number
  parent_id: number
  name: string
  leader: string
  phone: string
  email: string
  sort: number
  status: number
  created_at: string | null
  updated_at: string | null
  children?: DeptItem[]
}

/** GET /admin/v1/depts/tree —— 完整部门树（无分页） */
export function getDeptTree(): Promise<ApiEnvelope<DeptItem[]>> {
  return request<DeptItem[]>({ url: '/v1/depts/tree', method: 'get' })
}

/** GET /admin/v1/depts/:id —— 详情 */
export function getDept(id: number): Promise<ApiEnvelope<DeptItem>> {
  return request<DeptItem>({ url: `/v1/depts/${id}`, method: 'get' })
}

/** POST /admin/v1/depts —— 新增（sceneCreate：name 必填） */
export function createDept(data: Record<string, unknown>): Promise<ApiEnvelope<DeptItem>> {
  return request<DeptItem>({ url: '/v1/depts', method: 'post', data })
}

/** PUT /admin/v1/depts/:id —— 更新（选择性字段；防自指/成环在后端） */
export function updateDept(
  id: number,
  data: Record<string, unknown>,
): Promise<ApiEnvelope<DeptItem>> {
  return request<DeptItem>({ url: `/v1/depts/${id}`, method: 'put', data })
}

/** DELETE /admin/v1/depts/:id —— 删除（有子节点 → 422） */
export function deleteDept(id: number): Promise<ApiEnvelope<null>> {
  return request<null>({ url: `/v1/depts/${id}`, method: 'delete' })
}

/** PUT /admin/v1/depts/:id/status —— 启停 */
export function setDeptStatus(id: number, status: number): Promise<ApiEnvelope<DeptItem>> {
  return request<DeptItem>({ url: `/v1/depts/${id}/status`, method: 'put', data: { status } })
}
