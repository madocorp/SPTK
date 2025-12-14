
##########################

git clone https://github.com/libsdl-org/SDL.git SDL3

cd SDL3

git checkout release-3.2.x

mkdir build && cd build
cmake .. -DCMAKE_BUILD_TYPE=Release -DSDL_INSTALL=OFF
cmake --build . --config Release

############################

cd ..

git clone https://github.com/libsdl-org/SDL_ttf.git SDL3_ttf

cd SDL3_ttf

git checkout release-3.2.x // the same release name is a coincidence

mkdir build && cd build
cmake .. -DCMAKE_BUILD_TYPE=Release -DSDL3_DIR=../SDL3/build -DSDLTTF_INSTALL=OFF
cmake --build . --config Release

############################

Copy the .so files to the SdlWrapper directory!