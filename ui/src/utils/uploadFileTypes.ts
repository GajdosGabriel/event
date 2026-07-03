// Shared file-type rules for the event image/document upload components.
// PDFs are treated as "image" uploads because the backend already converts
// their first page into a thumb/large preview (see ImageVariantGenerator).
// DOC/DOCX have no such conversion, so they're stored as plain "file" uploads.

export const UPLOAD_ACCEPT =
  'image/*,.pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document'

const DOC_EXTENSIONS = /\.(doc|docx)$/i
const PDF_EXTENSION = /\.pdf$/i
const DOC_MIME_TYPES = new Set([
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
])

export function isImageLikeUpload(file: File): boolean {
  return file.type.startsWith('image/') || file.type === 'application/pdf' || PDF_EXTENSION.test(file.name)
}

function isDocUpload(file: File): boolean {
  return DOC_MIME_TYPES.has(file.type) || DOC_EXTENSIONS.test(file.name)
}

export function isAllowedUpload(file: File): boolean {
  return isImageLikeUpload(file) || isDocUpload(file)
}
