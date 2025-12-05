
typedef unsigned int Uint32;
typedef int Sint32;
typedef signed long int Sint64;
typedef unsigned long int Uint64;
typedef unsigned short int Uint16;
typedef unsigned char Uint8;

typedef Uint32 SDL_DisplayID;
typedef Uint32 SDL_WindowID;
typedef Uint32 SDL_KeyboardID;
typedef Uint16 SDL_Keymod;
typedef Uint64 SDL_WindowFlags;
typedef Uint32 SDL_AudioDeviceID;
typedef Uint32 SDL_SurfaceFlags;
typedef Uint32 SDL_PixelFormat;
typedef Sint32 SDL_Keycode;
typedef Uint16 SDL_Keymod;

typedef struct SDL_Window SDL_Window;
typedef struct SDL_Renderer SDL_Renderer;
typedef struct SDL_Keysym SDL_Keysym;
typedef struct SDL_KeyboardEvent SDL_KeyboardEvent;
typedef struct SDL_QuitEvent SDL_QuitEvent;
typedef struct SDL_Palette SDL_Palette;
typedef struct SDL_Texture SDL_Texture;
typedef struct SDL_QuitEvent
{
  int type;
  Uint32 reserved;
  Uint64 timestamp;
} SDL_QuitEvent;
typedef struct SDL_KeyboardEvent
{
  int type;
  Uint32 reserved;
  Uint64 timestamp;
  SDL_WindowID windowID;
  SDL_KeyboardID which;
  int scancode;
  SDL_Keycode key;
  SDL_Keymod mod;
  Uint16 raw;
  bool down;
  bool repeat;
} SDL_KeyboardEvent;
typedef union SDL_Event
{
  Uint32 type;
  SDL_KeyboardEvent key;
  Uint8 padding[128];
} SDL_Event;
typedef struct SDL_Rect
{
  int x;
  int y;
  int w;
  int h;
} SDL_Rect;
typedef struct SDL_FRect
{
  float x;
  float y;
  float w;
  float h;
} SDL_FRect;
typedef struct SDL_Color
{
  Uint8 r;
  Uint8 g;
  Uint8 b;
  Uint8 a;
} SDL_Color;
typedef struct SDL_Surface
{
  SDL_SurfaceFlags flags;
  SDL_PixelFormat format;
  int w;
  int h;
} SDL_Surface;

bool SDL_Init(Uint32 flags);
void SDL_Quit(void);
SDL_DisplayID SDL_GetPrimaryDisplay(void);
bool SDL_GetDisplayUsableBounds(SDL_DisplayID displayID, SDL_Rect *rect);
bool SDL_GetWindowBordersSize(SDL_Window* window, int* top, int* left, int* bottom, int* right);
bool SDL_SetWindowSize(SDL_Window *window, int w, int h);
void SDL_GetWindowSize(SDL_Window* window, int* w, int* h);
bool SDL_SetRenderViewport(SDL_Renderer* renderer, const SDL_Rect* rect);
bool SDL_SetWindowPosition(SDL_Window* window, int x, int y);
SDL_Window* SDL_CreateWindow(const char* title, int w, int h, SDL_WindowFlags flags);
SDL_Renderer* SDL_CreateRenderer(SDL_Window* window, const char* name);
bool SDL_SetRenderDrawColor(SDL_Renderer* renderer, Uint8 r, Uint8 g, Uint8 b, Uint8 a);
bool SDL_RenderClear(SDL_Renderer* renderer);
bool SDL_RenderPresent(SDL_Renderer* renderer);
bool SDL_SetWindowTitle(SDL_Window* window, const char* title);


int SDL_SetHint(const char* name, const char* value);
bool SDL_PollEvent(SDL_Event* event);
void SDL_DestroyRenderer(SDL_Renderer* renderer);
void SDL_DestroyWindow(SDL_Window* window);
bool SDL_RenderTexture(SDL_Renderer* renderer, SDL_Texture* texture, const SDL_FRect* srcrect, const SDL_FRect* dstrect);
SDL_Texture* SDL_CreateTexture(SDL_Renderer* renderer, Uint32 format, int access, int w, int h);
int SDL_LockTexture(SDL_Texture* texture, const SDL_Rect* rect, void** pixels, int* pitch);
void SDL_UnlockTexture(SDL_Texture* texture);
int SDL_SetTextureBlendMode(SDL_Texture* texture, int blendMode);
void SDL_DestroyTexture(SDL_Texture* texture);
const char* SDL_GetError(void);
bool SDL_SetTextureScaleMode(SDL_Texture* texture, int scaleMode);
bool SDL_SetRenderTarget(SDL_Renderer* renderer, SDL_Texture* texture);
SDL_Texture* SDL_CreateTextureFromSurface(SDL_Renderer* renderer, SDL_Surface* surface);
void SDL_DestroySurface(SDL_Surface* surface);
bool SDL_RenderLine(SDL_Renderer* renderer, float x1, float y1, float x2, float y2);
