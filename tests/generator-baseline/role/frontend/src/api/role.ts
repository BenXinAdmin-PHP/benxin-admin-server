/*
 * +----------------------------------------------------------------------
 * | @project   BenXinAdmin
 * | @mission   角色接口（CRUD + 状态 + 分配菜单 — /admin/v1/roles）
 * | @author    仗键天涯(daxing)
 * | @email     3442535897@qq.com
 * | @date      2026-06-12
 * +----------------------------------------------------------------------
 */
import { request, type ApiEnvelope, type PageResult } from '@/utils/request'

/** 角色行（列表/详情共用；详情额外带 menu_ids） */
export interface RoleItem {
  id: number
  name: string
  code: string
  sort: number
  status: number
  /** 数据范围：1全部 2本部门 3本部门及以下 4仅本人 5自定义（ADR-9） */
  data_scope: number
  remark: string
  created_at: string | null
  updated_at: string | null
}

/** 详情聚合：含已分配菜单 id */
export interface RoleDetail extends RoleItem {
  menu_ids: number[]
}

/** GET /admin/v1/roles —— 分页列表（keyword 模糊 name/code，status 精确） */
export function listRoles(
  params: Record<string, unknown>,
): Promise<ApiEnvelope<PageResult<RoleItem>>> {
  return request<PageResult<RoleItem>>({ url: '/v1/roles', method: 'get', params })
}

/** GET /admin/v1/roles/:id —— 详情（含 menu_ids） */
export function getRole(id: number): Promise<ApiEnvelope<RoleDetail>> {
  return request<RoleDetail>({ url: `/v1/roles/${id}`, method: 'get' })
}

/** POST /admin/v1/roles —— 新增（sceneCreate：name/code 必填） */
export function createRole(data: Record<string, unknown>): Promise<ApiEnvelope<RoleItem>> {
  return request<RoleItem>({ url: '/v1/roles', method: 'post', data })
}

/** PUT /admin/v1/roles/:id —— 更新（选择性字段） */
export function updateRole(
  id: number,
  data: Record<string, unknown>,
): Promise<ApiEnvelope<RoleItem>> {
  return request<RoleItem>({ url: `/v1/roles/${id}`, method: 'put', data })
}

/** DELETE /admin/v1/roles/:id —— 删除（super_admin/有管理员绑定 → 422） */
export function deleteRole(id: number): Promise<ApiEnvelope<null>> {
  return request<null>({ url: `/v1/roles/${id}`, method: 'delete' })
}

/** PUT /admin/v1/roles/:id/status —— 启停 */
export function setRoleStatus(id: number, status: number): Promise<ApiEnvelope<RoleItem>> {
  return request<RoleItem>({ url: `/v1/roles/${id}/status`, method: 'put', data: { status } })
}

/** GET /admin/v1/roles/:id/menus —— 已分配菜单 id 列表（分配弹窗回显） */
export function getRoleMenuIds(id: number): Promise<ApiEnvelope<number[]>> {
  return request<number[]>({ url: `/v1/roles/${id}/menus`, method: 'get' })
}

/** PUT /admin/v1/roles/:id/menus —— 覆盖式分配菜单（同步 Casbin） */
export function assignRoleMenus(id: number, menu_ids: number[]): Promise<ApiEnvelope<null>> {
  return request<null>({ url: `/v1/roles/${id}/menus`, method: 'put', data: { menu_ids } })
}
