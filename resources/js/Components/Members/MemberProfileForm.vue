<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AppInput from '@/Components/UI/AppInput.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppButton from '@/Components/UI/AppButton.vue'

interface ProfileData {
    date_of_birth: string | null
    gender: string | null
    marital_status: string | null
    address: string | null
    emergency_contact_name: string | null
    emergency_contact_relationship: string | null
    emergency_contact_phone: string | null
    membership_date?: string | null
    baptism_date?: string | null
    salvation_date?: string | null
}

const props = defineProps<{
    memberId: number
    profile: ProfileData | null
    canEditAdminFields: boolean
}>()

const emit = defineEmits<{ cancel: [] }>()

const form = useForm({
    date_of_birth:                  props.profile?.date_of_birth                  ?? '',
    gender:                         props.profile?.gender                         ?? '',
    marital_status:                 props.profile?.marital_status                 ?? '',
    address:                        props.profile?.address                        ?? '',
    emergency_contact_name:         props.profile?.emergency_contact_name         ?? '',
    emergency_contact_relationship: props.profile?.emergency_contact_relationship ?? '',
    emergency_contact_phone:        props.profile?.emergency_contact_phone        ?? '',
    membership_date:                props.profile?.membership_date                ?? '',
    baptism_date:                   props.profile?.baptism_date                   ?? '',
    salvation_date:                 props.profile?.salvation_date                 ?? '',
})

function submit() {
    form.put(`/dashboard/members/${props.memberId}/profile`, {
        onSuccess: () => emit('cancel'),
    })
}

const genderOptions = [
    { value: '',                  label: '— select —' },
    { value: 'male',              label: 'Male' },
    { value: 'female',            label: 'Female' },
    { value: 'other',             label: 'Other' },
    { value: 'prefer_not_to_say', label: 'Prefer not to say' },
]

const maritalOptions = [
    { value: '',         label: '— select —' },
    { value: 'single',   label: 'Single' },
    { value: 'married',  label: 'Married' },
    { value: 'widowed',  label: 'Widowed' },
    { value: 'divorced', label: 'Divorced' },
]

const inputCls = 'w-full px-3 py-2 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 placeholder:text-neutral-400 focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10 transition-colors'
</script>

<template>
    <form @submit.prevent="submit" class="space-y-5">

        <!-- Personal details -->
        <div>
            <p class="text-[10px] font-semibold uppercase tracking-widest text-neutral-400 mb-3">Personal</p>
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-neutral-600 mb-1">Date of birth</label>
                    <input type="date" v-model="form.date_of_birth" :class="inputCls" />
                    <p v-if="form.errors.date_of_birth" class="mt-0.5 text-xs text-rose-500">{{ form.errors.date_of_birth }}</p>
                </div>
                <AppSelect label="Gender" v-model="form.gender" :options="genderOptions" :error="form.errors.gender" />
                <AppSelect label="Marital status" v-model="form.marital_status" :options="maritalOptions" :error="form.errors.marital_status" />
                <div>
                    <label class="block text-xs font-medium text-neutral-600 mb-1">Address</label>
                    <textarea
                        v-model="form.address"
                        rows="2"
                        placeholder="123 Church Street, City…"
                        :class="[inputCls, 'resize-none']"
                    />
                    <p v-if="form.errors.address" class="mt-0.5 text-xs text-rose-500">{{ form.errors.address }}</p>
                </div>
            </div>
        </div>

        <!-- Emergency contact -->
        <div>
            <p class="text-[10px] font-semibold uppercase tracking-widest text-neutral-400 mb-3">Emergency contact</p>
            <div class="space-y-3">
                <AppInput label="Name" v-model="form.emergency_contact_name" :error="form.errors.emergency_contact_name" placeholder="Full name" />
                <AppInput label="Relationship" v-model="form.emergency_contact_relationship" :error="form.errors.emergency_contact_relationship" placeholder="e.g. Spouse, Parent" />
                <AppInput label="Phone" v-model="form.emergency_contact_phone" :error="form.errors.emergency_contact_phone" placeholder="+1 555 000 0000" />
            </div>
        </div>

        <!-- Church journey — admin only -->
        <div v-if="canEditAdminFields">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-neutral-400 mb-3">Church journey</p>
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-neutral-600 mb-1">Membership date</label>
                    <input type="date" v-model="form.membership_date" :class="inputCls" />
                    <p v-if="form.errors.membership_date" class="mt-0.5 text-xs text-rose-500">{{ form.errors.membership_date }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-neutral-600 mb-1">Baptism date</label>
                    <input type="date" v-model="form.baptism_date" :class="inputCls" />
                    <p v-if="form.errors.baptism_date" class="mt-0.5 text-xs text-rose-500">{{ form.errors.baptism_date }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-neutral-600 mb-1">Salvation date</label>
                    <input type="date" v-model="form.salvation_date" :class="inputCls" />
                    <p v-if="form.errors.salvation_date" class="mt-0.5 text-xs text-rose-500">{{ form.errors.salvation_date }}</p>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3 pt-1">
            <AppButton type="submit" size="sm" :loading="form.processing">Save profile</AppButton>
            <button
                type="button"
                @click="$emit('cancel')"
                class="text-sm text-neutral-500 hover:text-neutral-700 transition-colors"
            >Cancel</button>
        </div>

    </form>
</template>
