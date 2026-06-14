<!--
  +----------------------------------------------------------------------
  | @project   BenXinAdmin
  | @mission   管理员管理（bx:make 生成：XTable 配置化列表 + 编辑抽屉）
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
  createAdmin,
  deleteAdmin,
  listAdmins,
  setAdminStatus,
  updateAdmin,
} from '@/api/admin'
import type { Row, XTableConfig } from '@/components/XTable/types'
import type { XFormDrawerConfig } from '@/components/XFormDrawer/types'


const api = {
  list: listAdmins,
  save: createAdmin,
  update: updateAdmin,
  remove: deleteAdmin,
  status: setAdminStatus,
}

const config: XTableConfig = {
  api,
  rowKey: 'id',
  search: [
    { prop: 'status', label: '状态', type: 'select', dict: 'sys_normal_disable', width: 160 },
  ],
  columns: [
    { prop: 'id', label: 'ID', width: 70 },
    { prop: 'username', label: '登录账号' },
    { prop: 'password', label: '密码哈希' },
    { prop: 'nickname', label: '昵称' },
    { prop: 'avatar', label: '头像URL' },
    { prop: 'mobile', label: '手机号' },
    { prop: 'email', label: '邮箱' },
    { prop: 'dept_id', label: '所属部门ID' },
    { prop: 'status', label: '状态', type: 'switch', perm: 'system:admin:update', width: 80 },
    { prop: 'last_login_at', label: '最后登录时间' },
    { prop: 'last_login_ip', label: '最后登录IP' },
    { prop: 'remark', label: '备注' },
    { prop: 'created_at', label: '创建时间', type: 'time', sortable: true, width: 180 },
  ],
  toolbar: { create: { perm: 'system:admin:create', label: '新增管理员' } },
  rowActions: [
    { label: '编辑', emit: 'edit', perm: 'system:admin:update' },
    { label: '删除', emit: 'remove', perm: 'system:admin:delete', type: 'danger', confirm: true },
  ],
}

const formConfig: XFormDrawerConfig = {
  entity: '管理员',
  api,
  items: [
    { prop: 'username', label: '登录账号', type: 'input', disabledOnEdit: true },
    { prop: 'password', label: '密码哈希', type: 'textarea' },
    { prop: 'nickname', label: '昵称', type: 'input' },
    { prop: 'avatar', label: '头像URL', type: 'textarea' },
    { prop: 'mobile', label: '手机号', type: 'input' },
    { prop: 'email', label: '邮箱', type: 'input' },
    { prop: 'dept_id', label: '所属部门ID', type: 'input' },
    { prop: 'status', label: '状态', type: 'switch', activeValue: 1, inactiveValue: 0 },
    { prop: 'last_login_at', label: '最后登录时间', type: 'input' },
    { prop: 'last_login_ip', label: '最后登录IP', type: 'input' },
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
