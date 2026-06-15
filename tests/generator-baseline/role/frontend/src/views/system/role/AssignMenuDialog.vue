<!--
  +----------------------------------------------------------------------
  | @project   BenXinAdmin
  | @mission   分配菜单弹窗（bx:make 生成：ElTree 独立勾选，GET 回显 + PUT 覆盖提交）
  | @author    仗键天涯(daxing)
  | @email     3442535897@qq.com
  | @date      2026-06-12
  +----------------------------------------------------------------------
-->
<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import { ElMessage, type ElTree } from 'element-plus'
import { getMenuTree, type MenuItem } from '@/api/menu'
import { assignRoleMenus, getRoleMenuIds } from '@/api/role'

const props = defineProps<{ roleId: number; roleName: string }>()

const visible = defineModel<boolean>({ required: true })

const emit = defineEmits<{
  /** 分配成功（Casbin 已同步，换登录可验 enforce 变化） */
  success: []
}>()

const treeRef = ref<InstanceType<typeof ElTree>>()
const menuTree = ref<MenuItem[]>([])
const loading = ref(false)
const saving = ref(false)

// 打开时拉全量菜单树 + 已分配 menuIds 回显勾选。
// check-strictly 独立勾选语义：提交精确 id 集合、不做父子级联；
// 后端 profile 取数时自动补全祖先目录保证树连通（基线 §7），二者配合自洽。
watch(visible, async (val) => {
  if (!val) {
    return
  }
  loading.value = true
  try {
    const [treeResp, idsResp] = await Promise.all([getMenuTree(), getRoleMenuIds(props.roleId)])
    menuTree.value = treeResp.data
    await nextTick()
    treeRef.value?.setCheckedKeys(idsResp.data)
  } finally {
    loading.value = false
  }
})

async function submit() {
  const keys = (treeRef.value?.getCheckedKeys() ?? []) as number[]
  saving.value = true
  try {
    await assignRoleMenus(props.roleId, keys)
    ElMessage.success('分配成功')
    visible.value = false
    emit('success')
  } catch {
    // 非法 menu_id / super_admin 等 422 已由拦截器提示，后端整单回滚不留半套策略
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <el-dialog v-model="visible" :title="`分配菜单 —— ${roleName}`" width="520px">
    <div v-loading="loading" class="max-h-60vh overflow-y-auto">
      <el-tree
        ref="treeRef"
        :data="menuTree"
        :props="{ label: 'title', children: 'children' }"
        node-key="id"
        show-checkbox
        check-strictly
        default-expand-all
      />
    </div>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="saving" @click="submit">确定</el-button>
    </template>
  </el-dialog>
</template>
