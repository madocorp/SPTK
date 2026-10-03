typedef struct {
  void *opaque;
  unsigned int version;
  unsigned int width;
  unsigned int height;
  unsigned int format;
  unsigned int flags;
  unsigned int colormap_entries;
  unsigned int warning_or_error;
  char message[64];
} png_image;

int png_image_begin_read_from_memory(png_image *image, const void *memory, size_t size);
int png_image_finish_read(png_image *image, const void *background, void *buffer, int row_stride, void *colormap);
void png_image_free(png_image *image);
