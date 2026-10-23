cmake_minimum_required(VERSION 3.10)
project(face_extract LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

set(CMAKE_CXX_FLAGS_RELEASE "-O3")

find_package(ncnn REQUIRED)

# ── face_api: SCRFD detect + ArcFace align + MobileFaceNet embed, HTTP API ────
# Decodes images with bundled stb_image, so it needs ncnn only (no OpenCV) —
# this is the one that builds on the S2 phone and runs as the face API.
add_executable(face_api face_api.cpp)
target_link_libraries(face_api PRIVATE ncnn)

# ── face_extract: OpenCV based CLI used for parity runs on P1 ────────────────
# Only built when OpenCV dev headers are available.
find_package(OpenCV QUIET)
if(OpenCV_FOUND)
  add_executable(face_extract face_extract.cpp)
  target_link_libraries(face_extract PRIVATE ncnn ${OpenCV_LIBS})
endif()
