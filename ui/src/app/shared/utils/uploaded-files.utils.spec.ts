import { describe, expect, it } from 'vitest';
import { extractPrimaryImageUrl, extractUploadedFiles } from './uploaded-files.utils';

describe('uploaded-files utils', () => {
  it('should resolve primary image url from primary_image array', () => {
    const result = extractPrimaryImageUrl({
      primary_image: [
        {
          id: 4,
          original_url: '/storage/events/poster.jpg',
          is_primary: true
        }
      ]
    });

    expect(result).toBe('http://event-api.local/storage/events/poster.jpg');
  });

  it('should fallback to provided fallback image when primary_image is missing', () => {
    const result = extractPrimaryImageUrl({
      primary_image: []
    }, 'https://example.com/fallback.jpg');

    expect(result).toBe('https://example.com/fallback.jpg');
  });

  it('should expose primary_image entries as uploaded files', () => {
    const result = extractUploadedFiles({
      primary_image: [
        {
          id: 9,
          file_name: 'cover.jpg',
          original_url: '/storage/venues/cover.jpg',
          is_primary: true,
          mime_type: 'image/jpeg'
        }
      ]
    });

    expect(result).toEqual([
      {
        id: 9,
        name: 'cover.jpg',
        url: 'http://event-api.local/storage/venues/cover.jpg',
        previewUrl: 'http://event-api.local/storage/venues/cover.jpg',
        type: null,
        disk: null,
        sizeBytes: null,
        isPrimary: true,
        mimeType: 'image/jpeg'
      }
    ]);
  });

  it('should resolve primary image url from full_url field', () => {
    const result = extractPrimaryImageUrl({
      primary_image: [
        {
          id: 12,
          full_url: 'https://cdn.example.com/events/poster.jpg',
          is_primary: true
        }
      ]
    });

    expect(result).toBe('https://cdn.example.com/events/poster.jpg');
  });

  it('should resolve primary image url from wrapped data payload', () => {
    const result = extractPrimaryImageUrl({
      primary_image: {
        data: {
          id: 15,
          full_url: '/storage/canals/hero.jpg',
          is_primary: true
        }
      }
    });

    expect(result).toBe('http://event-api.local/storage/canals/hero.jpg');
  });

  it('should resolve primary image url from large and thumb object payload', () => {
    const result = extractPrimaryImageUrl({
      primary_image: {
        thumb: 'http://event-api.local/storage/event/85/image/thumb.jpeg',
        large: 'http://event-api.local/storage/event/85/image/large.jpeg'
      }
    });

    expect(result).toBe('http://event-api.local/storage/event/85/image/large.jpeg');
  });

  it('should use thumb for preview and large for opened image', () => {
    const result = extractUploadedFiles({
      primary_image: {
        thumb_image_url: 'http://event-api.local/storage/event/72/image/thumb.jpg',
        large_image_url: 'http://event-api.local/storage/event/72/image/large.jpg',
        original_name: 'poster.jpg',
        mime_type: 'image/jpeg'
      }
    });

    expect(result).toEqual([
      {
        id: undefined,
        name: 'poster.jpg',
        url: 'http://event-api.local/storage/event/72/image/large.jpg',
        previewUrl: 'http://event-api.local/storage/event/72/image/thumb.jpg',
        type: null,
        disk: null,
        sizeBytes: null,
        isPrimary: false,
        mimeType: 'image/jpeg'
      }
    ]);
  });

  it('should prefer direct large_image_url over raw path when both are present', () => {
    const result = extractPrimaryImageUrl({
      thumb_image_url: 'http://event-api.local/storage/event/72/image/thumb.jpg',
      large_image_url: 'http://event-api.local/storage/event/72/image/large.jpg',
      path: 'event/72/image/large.jpg'
    });

    expect(result).toBe('http://event-api.local/storage/event/72/image/large.jpg');
  });

  it('should prefer nested large_image_url over path inside primary_image payload', () => {
    const result = extractPrimaryImageUrl({
      primary_image: {
        thumb_image_url: 'http://event-api.local/storage/event/72/image/1Y70ET3JDxW9toNNE0cKtTt0pQeBIjvMWgEQykjm_thumb.jpg',
        large_image_url: 'http://event-api.local/storage/event/72/image/1Y70ET3JDxW9toNNE0cKtTt0pQeBIjvMWgEQykjm_large.jpg',
        path: 'event/72/image/1Y70ET3JDxW9toNNE0cKtTt0pQeBIjvMWgEQykjm_large.jpg'
      }
    });

    expect(result).toBe(
      'http://event-api.local/storage/event/72/image/1Y70ET3JDxW9toNNE0cKtTt0pQeBIjvMWgEQykjm_large.jpg'
    );
  });

  it('should merge files from multiple collections instead of returning only the first one', () => {
    const result = extractUploadedFiles({
      primary_image: [
        {
          id: 1,
          file_name: 'cover.jpg',
          original_url: '/storage/events/cover.jpg',
          is_primary: true,
          mime_type: 'image/jpeg'
        }
      ],
      attachments: [
        {
          id: 2,
          file_name: 'program.pdf',
          original_url: '/storage/events/program.pdf',
          mime_type: 'application/pdf'
        }
      ]
    });

    expect(result).toEqual([
      {
        id: 1,
        name: 'cover.jpg',
        url: 'http://event-api.local/storage/events/cover.jpg',
        previewUrl: 'http://event-api.local/storage/events/cover.jpg',
        type: null,
        disk: null,
        sizeBytes: null,
        isPrimary: true,
        mimeType: 'image/jpeg'
      },
      {
        id: 2,
        name: 'program.pdf',
        url: 'http://event-api.local/storage/events/program.pdf',
        previewUrl: 'http://event-api.local/storage/events/program.pdf',
        type: null,
        disk: null,
        sizeBytes: null,
        isPrimary: false,
        mimeType: 'application/pdf'
      }
    ]);
  });

  it('should deduplicate the same image represented in primary_image and files', () => {
    const result = extractUploadedFiles({
      primary_image: {
        thumb_image_url: 'http://event-api.local/storage/event/72/image/1Y70ET3JDxW9toNNE0cKtTt0pQeBIjvMWgEQykjm_thumb.jpg',
        large_image_url: 'http://event-api.local/storage/event/72/image/1Y70ET3JDxW9toNNE0cKtTt0pQeBIjvMWgEQykjm_large.jpg',
        original_name: 'poster.jpg',
        mime_type: 'image/jpeg',
        is_primary: true
      },
      files: [
        {
          id: 15,
          original_name: 'poster.jpg',
          thumb: 'event/72/image/1Y70ET3JDxW9toNNE0cKtTt0pQeBIjvMWgEQykjm_thumb.jpg',
          large: 'event/72/image/1Y70ET3JDxW9toNNE0cKtTt0pQeBIjvMWgEQykjm_large.jpg',
          mime_type: 'image/jpeg',
          type: 'image',
          is_primary: true
        }
      ]
    });

    expect(result).toHaveLength(1);
    expect(result[0]?.previewUrl).toBe(
      'http://event-api.local/storage/event/72/image/1Y70ET3JDxW9toNNE0cKtTt0pQeBIjvMWgEQykjm_thumb.jpg'
    );
    expect(result[0]?.url).toBe(
      'http://event-api.local/storage/event/72/image/1Y70ET3JDxW9toNNE0cKtTt0pQeBIjvMWgEQykjm_large.jpg'
    );
  });

  it('should prefer original file URLs over document placeholder previews', () => {
    const result = extractUploadedFiles({
      files: [
        {
          id: 87,
          name: 'pravidelne-mesacne-stretnutie-hnutia-pre-pomoc-rozvedenym-krestanom',
          original_name: 'pravidelne-mesacne-stretnutie-hnutia-pre-pomoc-rozvedenym-krestanom.pdf',
          mime_type: 'application/pdf',
          type: 'file',
          original_file_url: 'http://event-api.local/storage/event/102/file/ANDXC7XaQuVb0QSBK5RmpYNlsT6Ehai4iVL6leWj.pdf',
          thumb_image_url: 'http://event-api.local/images/document-placeholder.svg',
          large_image_url: 'http://event-api.local/images/document-placeholder.svg'
        }
      ]
    });

    expect(result).toEqual([
      {
        id: 87,
        name: 'pravidelne-mesacne-stretnutie-hnutia-pre-pomoc-rozvedenym-krestanom.pdf',
        url: 'http://event-api.local/storage/event/102/file/ANDXC7XaQuVb0QSBK5RmpYNlsT6Ehai4iVL6leWj.pdf',
        previewUrl: 'http://event-api.local/images/document-placeholder.svg',
        type: 'file',
        disk: null,
        sizeBytes: null,
        isPrimary: false,
        mimeType: 'application/pdf'
      }
    ]);
  });
});
