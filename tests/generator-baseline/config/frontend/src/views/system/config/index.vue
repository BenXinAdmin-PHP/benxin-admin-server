<!--
  +----------------------------------------------------------------------
  | @project   BenXinAdmin
  | @mission   配置中心管理（bx:make 生成：XTable 配置化列表 + 编辑抽屉）
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
  createConfig,
  deleteConfig,
  listConfigs,
  updateConfig,
} from '@/api/config'
import type { Row, XTableConfig } from '@/components/XTable/types'
import type { XFormDrawerConfig } from '@/components/XFormDrawer/types'


const api = {
  list: listConfigs,
  save: createConfig,
  update: updateConfig,
  remove: deleteConfig,
}

const config: XTableConfig = {
  api,
  rowKey: 'id',
  columns: [
    { prop: 'id', label: 'ID', width: 70 },
    { prop: 'name', label: '配置中文名' },
    { prop: 'group', label: '配置分组' },
    { prop: 'key', label: '配置键' },
    { prop: 'value', label: '配置值' },
    { prop: 'remark', label: '备注' },
    { prop: 'is_sensitive', label: '是否敏感' },
    { prop: 'value_type', label: '值类型' },
    { prop: 'sort', label: '排序' },
    { prop: 'created_at', label: '创建时间', type: 'time', sortable: true, width: 180 },
  ],
  toolbar: { create: { perm: 'system:config:create', label: '新增配置中心' } },
  rowActions: [
    { label: '编辑', emit: 'edit', perm: 'system:config:update' },
    { label: '删除', emit: 'remove', perm: 'system:config:delete', type: 'danger', confirm: true },
  ],
}

const formConfig: XFormDrawerConfig = {
  entity: '配置中心',
  api,
  items: [
    { prop: 'name', label: '配置中文名', type: 'input' },
    { prop: 'group', label: '配置分组', type: 'input', disabledOnEdit: true },
    { prop: 'key', label: '配置键', type: 'input' },
    { prop: 'value', label: '配置值', type: 'textarea' },
    { prop: 'remark', label: '备注', type: 'textarea' },
    { prop: 'is_sensitive', label: '是否敏感', type: 'input' },
    { prop: 'value_type', label: '值类型', type: 'input' },
    { prop: 'sort', label: '排序', type: 'number', min: 0, defaultValue: 0 },
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
