<!--
  +----------------------------------------------------------------------
  | @project   BenXinAdmin
  | @mission   字典类型管理（bx:make 生成：XTable 配置化列表 + 编辑抽屉）
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
  createDict,
  deleteDict,
  listDicts,
  setDictStatus,
  updateDict,
} from '@/api/dict'
import type { Row, XTableConfig } from '@/components/XTable/types'
import type { XFormDrawerConfig } from '@/components/XFormDrawer/types'


const api = {
  list: listDicts,
  save: createDict,
  update: updateDict,
  remove: deleteDict,
  status: setDictStatus,
}

const config: XTableConfig = {
  api,
  rowKey: 'id',
  search: [
    { prop: 'status', label: '状态', type: 'select', dict: 'sys_normal_disable', width: 160 },
  ],
  columns: [
    { prop: 'id', label: 'ID', width: 70 },
    { prop: 'name', label: '字典名称' },
    { prop: 'type', label: '字典类型标识' },
    { prop: 'status', label: '状态', type: 'switch', perm: 'system:dict:update', width: 80 },
    { prop: 'remark', label: '备注' },
    { prop: 'created_at', label: '创建时间', type: 'time', sortable: true, width: 180 },
  ],
  toolbar: { create: { perm: 'system:dict:create', label: '新增字典类型' } },
  rowActions: [
    { label: '编辑', emit: 'edit', perm: 'system:dict:update' },
    { label: '删除', emit: 'remove', perm: 'system:dict:delete', type: 'danger', confirm: true },
  ],
}

const formConfig: XFormDrawerConfig = {
  entity: '字典类型',
  api,
  items: [
    { prop: 'name', label: '字典名称', type: 'input' },
    { prop: 'type', label: '字典类型标识', type: 'input', disabledOnEdit: true },
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
