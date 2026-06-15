/*
 * +----------------------------------------------------------------------
 * | @project   BenXinAdmin
 * | @mission   配置中心接口（CRUD — /admin/v1/configs）
 * | @author    仗键天涯(daxing)
 * | @email     3442535897@qq.com
 * | @date      2026-06-12
 * +----------------------------------------------------------------------
 */
import { request, type ApiEnvelope, type PageResult } from '@/utils/request'

/** 配置中心行（列表/详情共用） */
export interface ConfigItem {
  id: number
  name: string
  group: string
  key: string
  value: string
  remark: string
  created_at: string | null
  updated_at: string | null
  is_sensitive: number
  value_type: string
  sort: number
}

/** GET /admin/v1/configs —— 分页列表 */
export function listConfigs(
  params: Record<string, unknown>,
): Promise<ApiEnvelope<PageResult<ConfigItem>>> {
  return request<PageResult<ConfigItem>>({ url: '/v1/configs', method: 'get', params })
}

/** GET /admin/v1/configs/:id —— 详情 */
export function getConfig(id: number): Promise<ApiEnvelope<ConfigItem>> {
  return request<ConfigItem>({ url: `/v1/configs/${id}`, method: 'get' })
}

/** POST /admin/v1/configs —— 新增 */
export function createConfig(data: Record<string, unknown>): Promise<ApiEnvelope<ConfigItem>> {
  return request<ConfigItem>({ url: '/v1/configs', method: 'post', data })
}

/** PUT /admin/v1/configs/:id —— 更新（选择性字段） */
export function updateConfig(
  id: number,
  data: Record<string, unknown>,
): Promise<ApiEnvelope<ConfigItem>> {
  return request<ConfigItem>({ url: `/v1/configs/${id}`, method: 'put', data })
}

/** DELETE /admin/v1/configs/:id —— 删除 */
export function deleteConfig(id: number): Promise<ApiEnvelope<null>> {
  return request<null>({ url: `/v1/configs/${id}`, method: 'delete' })
}
