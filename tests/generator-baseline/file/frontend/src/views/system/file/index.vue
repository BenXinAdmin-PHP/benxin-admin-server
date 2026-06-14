<!--
  +----------------------------------------------------------------------
  | @project   BenXinAdmin
  | @mission   文件管理（bx:make 生成：XTable 配置化列表 + 编辑抽屉）
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
  createFile,
  deleteFile,
  listFiles,
  updateFile,
} from '@/api/file'
import type { Row, XTableConfig } from '@/components/XTable/types'
import type { XFormDrawerConfig } from '@/components/XFormDrawer/types'


const api = {
  list: listFiles,
  save: createFile,
  update: updateFile,
  remove: deleteFile,
}

const config: XTableConfig = {
  api,
  rowKey: 'id',
  columns: [
    { prop: 'id', label: 'ID', width: 70 },
    { prop: 'original_name', label: '原始文件名' },
    { prop: 'file_name', label: '存储名' },
    { prop: 'path', label: '存储相对路径' },
    { prop: 'mime', label: '真实MIME' },
    { prop: 'ext', label: '扩展名' },
    { prop: 'size', label: '字节数' },
    { prop: 'storage', label: '驱动' },
    { prop: 'hash', label: '内容sha256' },
    { prop: 'url', label: '访问URL' },
    { prop: 'created_at', label: '创建时间', type: 'time', sortable: true, width: 180 },
  ],
  toolbar: { create: { perm: 'system:file:create', label: '新增文件' } },
  rowActions: [
    { label: '编辑', emit: 'edit', perm: 'system:file:update' },
    { label: '删除', emit: 'remove', perm: 'system:file:delete', type: 'danger', confirm: true },
  ],
}

const formConfig: XFormDrawerConfig = {
  entity: '文件',
  api,
  items: [
    { prop: 'original_name', label: '原始文件名', type: 'textarea' },
    { prop: 'file_name', label: '存储名', type: 'input' },
    { prop: 'path', label: '存储相对路径', type: 'textarea' },
    { prop: 'mime', label: '真实MIME', type: 'input' },
    { prop: 'ext', label: '扩展名', type: 'input' },
    { prop: 'size', label: '字节数', type: 'input' },
    { prop: 'storage', label: '驱动', type: 'input' },
    { prop: 'hash', label: '内容sha256', type: 'input' },
    { prop: 'url', label: '访问URL', type: 'textarea' },
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
