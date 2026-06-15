/*
 * +----------------------------------------------------------------------
 * | @project   BenXinAdmin
 * | @mission   菜单接口（树 + CRUD + 状态 — /admin/v1/menus）
 * | @author    仗键天涯(daxing)
 * | @email     3442535897@qq.com
 * | @date      2026-06-12
 * +----------------------------------------------------------------------
 */
import { request, type ApiEnvelope } from '@/utils/request'

/** 菜单树节点（管理端全字段；children 仅在有子节点时存在） */
export interface MenuItem {
  id: number
  parent_id: number
  /** 类型：1目录 2菜单 3按钮 */
  type: number
  /** 路由 name（前端用） */
  name: string
  title: string
  path: string
  component: string
  /** 权限标识，如 system:role:list（按钮类必填） */
  perms: string
  icon: string
  sort: number
  status: number
  /** 是否显示：1显示 0隐藏 */
  visible: number
  created_at: string | null
  updated_at: string | null
  children?: MenuItem[]
}

/** GET /admin/v1/menus/tree —— 完整菜单树（无分页） */
export function getMenuTree(): Promise<ApiEnvelope<MenuItem[]>> {
  return request<MenuItem[]>({ url: '/v1/menus/tree', method: 'get' })
}

/** GET /admin/v1/menus/:id —— 详情 */
export function getMenu(id: number): Promise<ApiEnvelope<MenuItem>> {
  return request<MenuItem>({ url: `/v1/menus/${id}`, method: 'get' })
}

/** POST /admin/v1/menus —— 新增（sceneCreate：type/title 必填） */
export function createMenu(data: Record<string, unknown>): Promise<ApiEnvelope<MenuItem>> {
  return request<MenuItem>({ url: '/v1/menus', method: 'post', data })
}

/** PUT /admin/v1/menus/:id —— 更新（选择性字段；防自指/成环在后端） */
export function updateMenu(
  id: number,
  data: Record<string, unknown>,
): Promise<ApiEnvelope<MenuItem>> {
  return request<MenuItem>({ url: `/v1/menus/${id}`, method: 'put', data })
}

/** DELETE /admin/v1/menus/:id —— 删除（有子节点 → 422；级联清 role_menu + casbin） */
export function deleteMenu(id: number): Promise<ApiEnvelope<null>> {
  return request<null>({ url: `/v1/menus/${id}`, method: 'delete' })
}

/** PUT /admin/v1/menus/:id/status —— 启停 */
export function setMenuStatus(id: number, status: number): Promise<ApiEnvelope<MenuItem>> {
  return request<MenuItem>({ url: `/v1/menus/${id}/status`, method: 'put', data: { status } })
}
