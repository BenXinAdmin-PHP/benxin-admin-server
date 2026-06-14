<!--
  +----------------------------------------------------------------------
  | @project   BenXinAdmin
  | @mission   部门管理（bx:make 生成：树形 XTable 整树无分页 + 编辑抽屉）
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
  createDept,
  deleteDept,
  getDeptTree,
  setDeptStatus,
  updateDept,
} from '@/api/dept'
import type { Row, XTableConfig } from '@/components/XTable/types'
import type { XFormDrawerConfig } from '@/components/XFormDrawer/types'


const api = {
  list: getDeptTree,
  save: createDept,
  update: updateDept,
  remove: deleteDept,
  status: setDeptStatus,
}

const config: XTableConfig = {
  api,
  rowKey: 'id',
  // ★ 树形范式：取 GET depts/tree 整树、无分页，row-key + tree-props 缩进展开
  tree: true,
  defaultExpandAll: true,
  columns: [
    { prop: 'parent_id', label: '父级ID' },
    { prop: 'name', label: '部门名称' },
    { prop: 'leader', label: '负责人' },
    { prop: 'phone', label: '联系电话' },
    { prop: 'email', label: '邮箱' },
    { prop: 'sort', label: '排序' },
    { prop: 'status', label: '状态', type: 'switch', perm: 'system:dept:update', width: 80 },
  ],
  toolbar: { create: { perm: 'system:dept:create', label: '新增部门' } },
  rowActions: [
    { label: '新增下级', emit: 'addChild', perm: 'system:dept:create' },
    { label: '编辑', emit: 'edit', perm: 'system:dept:update' },
    {
      label: '删除',
      emit: 'remove',
      perm: 'system:dept:delete',
      type: 'danger',
      confirm: true, // 有子节点后端拒删 422
    },
  ],
}

/** 父级部门树：虚拟根「顶级」(id=0) */
async function parentTreeData(): Promise<Row[]> {
  const { data } = await getDeptTree()
  return [{ id: 0, name: '顶级', children: data }]
}

const formConfig: XFormDrawerConfig = {
  entity: '部门',
  api,
  items: [
    {
      prop: 'parent_id',
      label: '父级部门',
      type: 'treeSelect',
      checkStrictly: true,
      treeData: parentTreeData,
      defaultValue: 0,
    },
    { prop: 'name', label: '部门名称', type: 'input', required: true },
    { prop: 'leader', label: '负责人', type: 'input' },
    { prop: 'phone', label: '联系电话', type: 'input' },
    { prop: 'email', label: '邮箱', type: 'input' },
    { prop: 'sort', label: '排序', type: 'number', min: 0, defaultValue: 0 },
    { prop: 'status', label: '状态', type: 'switch', activeValue: 1, inactiveValue: 0 },
  ],
}

const tableRef = ref<InstanceType<typeof XTable>>()
const drawerRef = ref<InstanceType<typeof XFormDrawer>>()

function onAction(name: string, row: Row | null) {
  if (name === 'create') {
    drawerRef.value?.open('create')
  } else if (name === 'addChild' && row) {
    // 行内「新增下级」：预置 parent_id 的 create 场景
    drawerRef.value?.open('create', { parent_id: row.id })
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
