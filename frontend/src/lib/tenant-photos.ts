export const photoLimit = 5
export function photoSelectionError(files: ReadonlyArray<Pick<File, 'name' | 'size' | 'type'>>, existing = 0): string {
  if (files.length + existing > photoLimit) return `Mỗi yêu cầu tối đa 5 ảnh. Bạn có thể bổ sung tối đa ${Math.max(0, photoLimit - existing)} ảnh.`
  for (const file of files) {
    if (!/\.(jpe?g|png|webp)$/i.test(file.name) || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return 'Chỉ chọn ảnh JPG, PNG hoặc WebP.'
    if (!file.size) return 'Có ảnh rỗng hoặc không đọc được. Hãy chọn lại ảnh.'
    if (file.size > 5 * 1024 * 1024) return 'Mỗi ảnh tối đa 5 MB. Hãy chọn ảnh nhỏ hơn.'
  }
  return ''
}
export function photoFormData(files: readonly File[]) {
  const data = new FormData()
  for (const file of files) data.append('photos[]', file, file.name)
  return data
}
