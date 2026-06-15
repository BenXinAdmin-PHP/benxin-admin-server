/*
 * +----------------------------------------------------------------------
 * | @project   BenXinAdmin
 * | @mission   岗位接口（CRUD + 状态 — /admin/v1/posts）
 * | @author    仗键天涯(daxing)
 * | @email     3442535897@qq.com
 * | @date      2026-06-12
 * +----------------------------------------------------------------------
 */
import { request, type ApiEnvelope, type PageResult } from '@/utils/request'

/** 岗位行（列表/详情共用） */
export interface PostItem {
  id: number
  code: string
  name: string
  sort: number
  status: number
  remark: string
  created_at: string | null
  updated_at: string | null
}

/** GET /admin/v1/posts —— 分页列表（keyword 模糊 code/name，status 精确） */
export function listPosts(
  params: Record<string, unknown>,
): Promise<ApiEnvelope<PageResult<PostItem>>> {
  return request<PageResult<PostItem>>({ url: '/v1/posts', method: 'get', params })
}

/** GET /admin/v1/posts/:id —— 详情 */
export function getPost(id: number): Promise<ApiEnvelope<PostItem>> {
  return request<PostItem>({ url: `/v1/posts/${id}`, method: 'get' })
}

/** POST /admin/v1/posts —— 新增（sceneCreate：code/name 必填） */
export function createPost(data: Record<string, unknown>): Promise<ApiEnvelope<PostItem>> {
  return request<PostItem>({ url: '/v1/posts', method: 'post', data })
}

/** PUT /admin/v1/posts/:id —— 更新（选择性字段） */
export function updatePost(
  id: number,
  data: Record<string, unknown>,
): Promise<ApiEnvelope<PostItem>> {
  return request<PostItem>({ url: `/v1/posts/${id}`, method: 'put', data })
}

/** DELETE /admin/v1/posts/:id —— 删除（有管理员绑定 → 422） */
export function deletePost(id: number): Promise<ApiEnvelope<null>> {
  return request<null>({ url: `/v1/posts/${id}`, method: 'delete' })
}

/** PUT /admin/v1/posts/:id/status —— 启停 */
export function setPostStatus(id: number, status: number): Promise<ApiEnvelope<PostItem>> {
  return request<PostItem>({ url: `/v1/posts/${id}/status`, method: 'put', data: { status } })
}
