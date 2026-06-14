<!--
  +----------------------------------------------------------------------
  | @project   BenXinAdmin
  | @mission   岗位管理（bx:make 生成：XTable 配置化列表 + 编辑抽屉）
  | @author    仗键天涯(daxing)
  | @email     3442535897@qq.com
  | @date      2026-06-12
  +----------------------------------------------------------------------
-->
<script setup lang="ts">
import { ref } from 'vue'
import XTable from '@/components/XTable/index.vue'
import XFormDrawer from '@/components/XFormDrawer/index.vue'
import {
  createPost,
  deletePost,
  listPosts,
  setPostStatus,
  updatePost,
} from '@/api/post'
import type { Row, XTableConfig } from '@/components/XTable/types'
import type { XFormDrawerConfig } from '@/components/XFormDrawer/types'


const api = {
  list: listPosts,
  save: createPost,
  update: updatePost,
  remove: deletePost,
  status: setPostStatus,
}

const config: XTableConfig = {
  api,
  rowKey: 'id',
  search: [
    { prop: 'keyword', label: '关键词', type: 'input', placeholder: '岗位编码/岗位名称模糊查询' },
    { prop: 'status', label: '状态', type: 'select', dict: 'sys_normal_disable', width: 160 },
  ],
  columns: [
    { prop: 'id', label: 'ID', width: 70 },
    { prop: 'code', label: '岗位编码' },
    { prop: 'name', label: '岗位名称' },
    { prop: 'sort', label: '排序' },
    { prop: 'status', label: '状态', type: 'switch', perm: 'system:post:update', width: 80 },
    { prop: 'remark', label: '备注' },
    { prop: 'created_at', label: '创建时间', type: 'time', sortable: true, width: 180 },
  ],
  toolbar: { create: { perm: 'system:post:create', label: '新增岗位' } },
  rowActions: [
    { label: '编辑', emit: 'edit', perm: 'system:post:update' },
    { label: '删除', emit: 'remove', perm: 'system:post:delete', type: 'danger', confirm: true },
  ],
}

const formConfig: XFormDrawerConfig = {
  entity: '岗位',
  api,
  items: [
    { prop: 'code', label: '岗位编码', type: 'input', required: true, disabledOnEdit: true },
    { prop: 'name', label: '岗位名称', type: 'input', required: true },
    { prop: 'sort', label: '排序', type: 'number', min: 0, defaultValue: 0 },
    { prop: 'status', label: '状态', type: 'switch', activeValue: 1, inactiveValue: 0 },
    { prop: 'remark', label: '备注', type: 'textarea' },
  ],
}

const tableRef = ref<InstanceType<typeof XTable>>()
const drawerRef = ref<InstanceType<typeof XFormDrawer>>()

function onAction(name: string, row: Row | null) {
  if (name === 'create') {
    drawerRef.value?.open('create')
  } else if (name === 'edit' && row) {
    drawerRef.value?.open('update', row)
  }
}
</script>

<template>
  <el-card shadow="never">
    <XTable ref="tableRef" :config="config" @action="onAction" />
  </el-card>

  <XFormDrawer ref="drawerRef" :config="formConfig" @success="tableRef?.reload()" />
</template>
