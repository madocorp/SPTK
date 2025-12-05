
typedef unsigned int size_t;
typedef unsigned char Uint8;
typedef unsigned int Uint32;
typedef Uint32 SDL_SurfaceFlags;
typedef Uint32 SDL_PixelFormat;

typedef struct TTF_Font TTF_Font;
typedef struct SDL_Rect
{
  int x;
  int y;
  int w;
  int h;
} SDL_Rect;
typedef struct SDL_Color
{
  Uint8 r;
  Uint8 g;
  Uint8 b;
  Uint8 a;
} SDL_Color;
typedef struct SDL_Palette SDL_Palette;
typedef struct SDL_Surface
{
  SDL_SurfaceFlags flags;
  SDL_PixelFormat format;
  int w;
  int h;
} SDL_Surface;

bool TTF_Init(void);
TTF_Font* TTF_OpenFont(const char* file, float ptsize);
int TTF_GetFontAscent(const TTF_Font *font);
int TTF_GetFontDescent(const TTF_Font *font);
void TTF_CloseFont(TTF_Font *font);
SDL_Surface* TTF_RenderText_Blended(TTF_Font* font, const char* text, size_t length, SDL_Color fg);
SDL_Surface* TTF_RenderText_Shaded(TTF_Font* font, const char* text, size_t length, SDL_Color fg, SDL_Color bg);
SDL_Surface* TTF_RenderText_Solid(TTF_Font *font, const char *text, size_t length, SDL_Color fg);
void SDL_DestroySurface(SDL_Surface *surface);
void TTF_Quit(void);
void TTF_SetFontHinting(TTF_Font *font, int hinting);