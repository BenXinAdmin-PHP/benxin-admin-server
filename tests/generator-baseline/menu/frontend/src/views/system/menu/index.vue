<!--
  +----------------------------------------------------------------------
  | @project   BenXinAdmin
  | @mission   菜单管理（bx:make 生成：树形 XTable 整树无分页 + 编辑抽屉）
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
  createMenu,
  deleteMenu,
  getMenuTree,
  setMenuStatus,
  updateMenu,
  type MenuItem,
} from '@/api/menu'
import type { OptionItem, Row, XTableConfig } from '@/components/XTable/types'
import type { XFormDrawerConfig } from '@/components/XFormDrawer/types'

/** 菜单类型（与后端 1目录 2菜单 3按钮 对齐；静态枚举不走字典） */
const TYPE_OPTIONS: OptionItem[] = [
  { label: '目录', value: 1, tagType: 'warning' },
  { label: '菜单', value: 2, tagType: 'primary' },
  { label: '按钮', value: 3, tagType: 'info' },
]

const api = {
  list: getMenuTree,
  save: createMenu,
  update: updateMenu,
  remove: deleteMenu,
  status: setMenuStatus,
}

const config: XTableConfig = {
  api,
  rowKey: 'id',
  // ★ 树形范式：取 GET menus/tree 整树、无分页，row-key + tree-props 缩进展开
  tree: true,
  defaultExpandAll: true,
  columns: [
    { prop: 'title', label: '标题', minWidth: 180 },
    { prop: 'type', label: '类型', type: 'dictTag', options: TYPE_OPTIONS, width: 80, align: 'center' },
    { prop: 'path', label: '路由路径', minWidth: 130, showOverflowTooltip: true },
    { prop: 'component', label: '组件', minWidth: 160, showOverflowTooltip: true },
    { prop: 'perms', label: '权限标识', minWidth: 170, showOverflowTooltip: true },
    { prop: 'sort', label: '排序', width: 70, align: 'center' },
    { prop: 'status', label: '状态', type: 'switch', perm: 'system:menu:update', width: 80 },
  ],
  toolbar: { create: { perm: 'system:menu:create', label: '新增菜单' } },
  actionsWidth: 200,
  rowActions: [
    {
      label: '新增下级',
      emit: 'addChild',
      perm: 'system:menu:create',
      // 按钮节点是叶子，不可挂子级
      show: (row) => row.type !== 3,
    },
    { label: '编辑', emit: 'edit', perm: 'system:menu:update' },
    {
      label: '删除',
      emit: 'remove',
      perm: 'system:menu:delete',
      type: 'danger',
      confirm: true, // 有子节点后端拒删 422；删除级联清 role_menu + casbin 策略
    },
  ],
}

/** 父级菜单树：虚拟根「顶级」(id=0) + 过滤按钮节点（按钮不可作父级） */
async function parentTreeData(): Promise<Row[]> {
  const { data } = await getMenuTree()
  const strip = (nodes: MenuItem[]): MenuItem[] =>
    nodes
      .filter((n) => n.type !== 3)
      .map((n) => ({ ...n, children: n.children ? strip(n.children) : undefined }))
  return [{ id: 0, title: '顶级', children: strip(data) }]
}

// ★ 进阶范式（模块特化）：type 目录/菜单/按钮切换时字段显隐联动，
// 隐藏字段不参与提交，后端 normalizeByType 兜底归一化。
const isNav = (form: Row) => Number(form.type) !== 3 // 目录/菜单
const isMenu = (form: Row) => Number(form.type) === 2
const isButton = (form: Row) => Number(form.type) === 3

const formConfig: XFormDrawerConfig = {
  entity: '菜单',
  api,
  items: [
    {
      prop: 'parent_id',
      label: '父级菜单',
      type: 'treeSelect',
      checkStrictly: true,
      treeData: parentTreeData,
      defaultValue: 0,
    },
    { prop: 'type', label: '菜单类型', type: 'radio', options: TYPE_OPTIONS, required: true, defaultValue: 2 },
    { prop: 'title', label: '标题', type: 'input', required: true },
    {
      prop: 'name',
      label: '路由 name',
      type: 'input',
      visible: isNav,
      tip: '前端路由记录的 name，如 SystemRole',
    },
    { prop: 'path', label: '路由路径', type: 'input', visible: isNav, tip: '如 /system/role' },
    {
      prop: 'component',
      label: '组件路径',
      type: 'input',
      visible: isMenu,
      tip: 'src/views/ 下的组件路径，如 system/role/index',
    },
    {
      prop: 'perms',
      label: '权限标识',
      type: 'input',
      required: true,
      visible: isButton,
      tip: '与 Casbin enforce 同源，如 system:role:list；启用态唯一',
    },
    { prop: 'icon', label: '图标', type: 'input', visible: isNav, tip: 'Element Plus Icons 图标名' },
    { prop: 'sort', label: '排序', type: 'number', min: 0, defaultValue: 0 },
    { prop: 'status', label: '状态', type: 'switch', activeValue: 1, inactiveValue: 0 },
    {
      prop: 'visible',
      label: '是否显示',
      type: 'switch',
      activeValue: 1,
      inactiveValue: 0,
      visible: isNav,
      tip: '隐藏后不出现在侧边菜单，路由仍可达',
    },
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
