function isHttpUrl(value: string): boolean {
  if (!URL.canParse(value)) {
    return false
  }

  return ['http:', 'https:'].includes(new URL(value).protocol)
}

export const targetNameRules = [
  (value: string) => value.trim().length > 0 || 'Name is required.',
  (value: string) => value.length <= 255 || 'Name is at most 255 characters.',
]

export const targetUrlRules = [
  (value: string) => value.length > 0 || 'URL is required.',
  (value: string) => isHttpUrl(value) || 'Use a full http:// or https:// URL.',
]
