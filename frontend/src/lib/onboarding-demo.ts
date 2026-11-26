import { reactive } from 'vue'
export type JoinStatus = 'pending' | 'approved' | 'rejected' | 'withdrawn'
export type JoinRequest = { id: number; name: string; phone: string; status: JoinStatus; reason: string; mine?: boolean; room?: string }
export const onboardingDemo = reactive({
  afterRegister: 'start', rentalRequests: [] as { room: string; date: string; people: number }[],
  registered: false, name: 'Nguyễn Minh An', phone: '0900000000',
  property: { name: 'Nhà trọ An Bình', address: 'Thủ Đức, TP. Hồ Chí Minh', contact: 'Chị Linh', phone: '0901234567' },
  created: false, invite: 'ANBINH26', inviteActive: true,
  requests: [
    { id: 101, name: 'Trần Ngọc Mai', phone: '0900000123', status: 'pending', reason: '' },
    { id: 102, name: 'Lê Hoàng Nam', phone: '0900000456', status: 'pending', reason: '' },
  ] as JoinRequest[],
})
