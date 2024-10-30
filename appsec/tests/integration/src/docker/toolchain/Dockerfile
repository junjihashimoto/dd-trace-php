FROM debian:latest AS toolchain

ARG LLVM_VERSION=16.0.6
ARG ARCH

COPY CHECKSUMS /CHECKSUMS

RUN echo "Building LLVM ${LLVM_VERSION} on ${ARCH}"

RUN apt-get update -y && \
  apt-get upgrade -y && \
  wget https://apt.llvm.org/llvm.sh && \
  chmod +x llvm.sh && \
  ./llvm.sh ${LLVM_VERSION} all
