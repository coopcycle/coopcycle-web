export const storeFixture = {
    selectedTasks: [
      '/api/tasks/733'
    ],
    taskListGroupMode: 'GROUP_MODE_FOLDERS',
    logistics: {
      date: '2024-01-08T23:00:00.000Z',
      entities: {
        tasks: {
          ids: [
            '/api/tasks/730',
            '/api/tasks/731',
            '/api/tasks/727',
            '/api/tasks/728',
            '/api/tasks/729',
            '/api/tasks/732',
            '/api/tasks/733',
            '/api/tasks/734',
            '/api/tasks/735',
            '/api/tasks/736'
          ],
          entities: {
            '/api/tasks/730': {
              '@context': '/api/contexts/Task',
              '@id': '/api/tasks/730',
              '@type': 'Task',
              id: 730,
              type: 'PICKUP',
              status: 'TODO',
              group: null,
              after: '2024-01-09T00:00:00+01:00',
              before: '2024-01-09T23:59:59+01:00',
              isAssigned: true,
              orgName: 'test',
              images: [],
              doneAfter: '2024-01-09T00:00:00+01:00',
              doneBefore: '2024-01-09T23:59:59+01:00',
              assignedTo: 'admin',
              previous: null,
              next: '/api/tasks/731',
              packages: [],
              tour: {
                '@id': '/api/tours/111',
                name: 'tour 1',
                position: 1
              }
            },
            '/api/tasks/731': {
              '@context': '/api/contexts/Task',
              '@id': '/api/tasks/731',
              '@type': 'Task',
              id: 731,
              type: 'DROPOFF',
              status: 'TODO',
              group: null,
              after: '2024-01-09T00:00:00+01:00',
              before: '2024-01-09T23:59:59+01:00',
              isAssigned: true,
              doneAfter: '2024-01-09T00:00:00+01:00',
              doneBefore: '2024-01-09T23:59:59+01:00',
              assignedTo: 'admin',
              previous: '/api/tasks/730',
              next: null,
              packages: [],
              tour: {
                '@id': '/api/tours/111',
                name: 'tour 1',
                position: 2
              }
            },
            '/api/tasks/727': {
              '@context': '/api/contexts/Task',
              '@id': '/api/tasks/727',
              '@type': 'Task',
              id: 727,
              type: 'DROPOFF',
              status: 'TODO',
              group: {
                '@id': '/api/task_groups/22',
                '@type': 'TaskGroup',
                id: 22,
                name: 'mon groupe',
                tags: []
              },
              after: '2024-01-09T00:00:00+01:00',
              before: '2024-01-09T23:59:59+01:00',
              isAssigned: true,
              doneAfter: '2024-01-09T00:00:00+01:00',
              doneBefore: '2024-01-09T23:59:59+01:00',
              assignedTo: 'admin',
              previous: null,
              next: null,
              tour: {
                '@id': '/api/tours/111',
                name: 'tour 1',
                position: 3
              }
            },
            '/api/tasks/728': {
              '@context': '/api/contexts/Task',
              '@id': '/api/tasks/728',
              '@type': 'Task',
              id: 728,
              type: 'DROPOFF',
              status: 'TODO',
              comments: '',
              createdAt: '2024-01-08T14:34:45+01:00',
              updatedAt: '2024-01-09T11:01:58+01:00',
              group: {
                '@id': '/api/task_groups/22',
                '@type': 'TaskGroup',
                id: 22,
                name: 'mon groupe',
                tags: []
              },
              after: '2024-01-09T00:00:00+01:00',
              before: '2024-01-09T23:59:59+01:00',
              isAssigned: false,
              orgName: '',
              images: [],
              doneAfter: '2024-01-09T00:00:00+01:00',
              doneBefore: '2024-01-09T23:59:59+01:00',
              assignedTo: null,
              previous: null,
              next: null,
              packages: [],
              tour: null
            },
            '/api/tasks/729': {
              '@context': '/api/contexts/Task',
              '@id': '/api/tasks/729',
              '@type': 'Task',
              id: 729,
              type: 'DROPOFF',
              status: 'TODO',
              group: null,
              after: '2024-01-09T00:00:00+01:00',
              before: '2024-01-09T23:59:59+01:00',
              isAssigned: true,
              orgName: '',
              images: [],
              doneAfter: '2024-01-09T00:00:00+01:00',
              doneBefore: '2024-01-09T23:59:59+01:00',
              assignedTo: 'admin',
              previous: null,
              next: null,
              packages: [],
              tour: {
                '@id': '/api/tours/111',
                name: 'tour 1',
                position: 0
              }
            },
            '/api/tasks/732': {
              '@context': '/api/contexts/Task',
              '@id': '/api/tasks/732',
              '@type': 'Task',
              id: 732,
              type: 'DROPOFF',
              status: 'TODO',
              group: null,e: '2024-01-09T23:59:59+01:00',
              assignedTo: null,
              previous: null,
              next: null,
              packages: [],
              tour: {
                '@context': '/api/contexts/Tour',
                '@id': '/api/tours/114',
                '@type': 'Tour',
                name: 'ma tournée',
                items: [
                  {
                    '@context': '/api/contexts/Task',
                    '@id': '/api/tasks/732',
                    '@type': 'Task',
                    id: 732,
                    type: 'DROPOFF',
                    status: 'TODO',
                    group: null,
                    after: '2024-01-09T00:00:00+01:00',
                    before: '2024-01-09T23:59:59+01:00',
                    isAssigned: false,
                    orgName: '',
                    images: [],
                    doneAfter: '2024-01-09T00:00:00+01:00',
                    doneBefore: '2024-01-09T23:59:59+01:00',
                    assignedTo: null,
                    previous: null,
                    next: null,
                    packages: [],
                    tour: {
                      '@context': '/api/contexts/Tour',
                      '@id': '/api/tours/114',
                      '@type': 'Tour',
                      name: 'ma tournée',
                      items: [],
                      distance: 0,
                      duration: 0,
                      polyline: '',
                      date: '2024-01-09',
                      createdAt: '2024-01-09T12:46:23+01:00',
                      updatedAt: '2024-01-09T12:46:23+01:00',
                      itemIds: [],
                      position: 0
                    }
                  }
                ],
                date: '2024-01-09',
                createdAt: '2024-01-09T12:46:23+01:00',
                updatedAt: '2024-01-09T12:46:36+01:00',
                itemIds: [
                  '/api/tasks/732'
                ],
                tasks: [
                  {
                    '@context': '/api/contexts/Task',
                    '@id': '/api/tasks/732',
                    '@type': 'Task',
                    id: 732,
                    type: 'DROPOFF',
                    status: 'TODO',
                    group: null,
                    after: '2024-01-09T00:00:00+01:00',
                    before: '2024-01-09T23:59:59+01:00',
                    isAssigned: false,
                    orgName: '',
                    images: [],
                    doneAfter: '2024-01-09T00:00:00+01:00',
                    doneBefore: '2024-01-09T23:59:59+01:00',
                    assignedTo: null,
                    previous: null,
                    next: null,
                    packages: [],
                    tour: null
                  }
                ],
                position: 1
              }
            },
            '/api/tasks/733': {
              '@context': '/api/contexts/Task',
              '@id': '/api/tasks/733',
              '@type': 'Task',
              id: 733,
              type: 'DROPOFF',
              status: 'TODO',
              group: null,
              after: '2024-01-09T00:00:00+01:00',
              before: '2024-01-09T23:59:59+01:00',
              isAssigned: false,
              orgName: '',
              images: [],
              doneAfter: '2024-01-09T00:00:00+01:00',
              doneBefore: '2024-01-09T23:59:59+01:00',
              assignedTo: null,
              previous: null,
              next: null,
              packages: [],
              tour: {
                '@context': '/api/contexts/Tour',
                '@id': '/api/tours/114',
                '@type': 'Tour',
                name: 'ma tournée',
                items: [
                  {
                    '@context': '/api/contexts/Task',
                    '@id': '/api/tasks/732',
                    '@type': 'Task',
                    id: 732,
                    type: 'DROPOFF',
                    status: 'TODO',
                    group: null,
                    after: '2024-01-09T00:00:00+01:00',
                    before: '2024-01-09T23:59:59+01:00',
                    isAssigned: false,
                    orgName: '',
                    images: [],
                    doneAfter: '2024-01-09T00:00:00+01:00',
                    doneBefore: '2024-01-09T23:59:59+01:00',
                    assignedTo: null,
                    previous: null,
                    next: null,
                    packages: [],
                    tour: {
                      '@context': '/api/contexts/Tour',
                      '@id': '/api/tours/114',
                      '@type': 'Tour',
                      name: 'ma tournée',
                      items: [],
                      distance: 0,
                      duration: 0,
                      polyline: '',
                      date: '2024-01-09',
                      createdAt: '2024-01-09T12:46:23+01:00',
                      updatedAt: '2024-01-09T12:46:23+01:00',
                      itemIds: [],
                      position: 0
                    }
                  }
                ],
                distance: 0,
                duration: 0,
                polyline: '',
                date: '2024-01-09',
                createdAt: '2024-01-09T12:46:23+01:00',
                updatedAt: '2024-01-09T12:46:36+01:00',
                itemIds: [
                  '/api/tasks/732'
                ],
                tasks: [
                  {
                    '@context': '/api/contexts/Task',
                    '@id': '/api/tasks/732',
                    '@type': 'Task',
                    id: 732,
                    type: 'DROPOFF',
                    status: 'TODO',
                    group: null,
                    after: '2024-01-09T00:00:00+01:00',
                    before: '2024-01-09T23:59:59+01:00',
                    isAssigned: false,
                    orgName: '',
                    images: [],
                    doneAfter: '2024-01-09T00:00:00+01:00',
                    doneBefore: '2024-01-09T23:59:59+01:00',
                    assignedTo: null,
                    previous: null,
                    next: null,
                    packages: [],
                    tour: null
                  }
                ],
                position: 0
              }
            },
            '/api/tasks/734': {
              '@context': '/api/contexts/Task',
              '@id': '/api/tasks/734',
              '@type': 'Task',
              id: 734,
              type: 'DROPOFF',
              status: 'TODO',
              group: null,
              after: '2024-01-09T00:00:00+01:00',
              before: '2024-01-09T23:59:59+01:00',
              isAssigned: false,
              orgName: '',
              images: [],
              doneAfter: '2024-01-09T00:00:00+01:00',
              doneBefore: '2024-01-09T23:59:59+01:00',
              assignedTo: null,
              previous: '/api/tasks/735',
              next: null,
              packages: [],
              tour: null
            },
            '/api/tasks/735': {
              '@context': '/api/contexts/Task',
              '@id': '/api/tasks/735',
              '@type': 'Task',
              id: 735,
              type: 'PICKUP',
              status: 'TODO',
              group: null,
              after: '2024-01-09T00:00:00+01:00',
              before: '2024-01-09T23:59:59+01:00',
              isAssigned: false,
              orgName: '',
              images: [],
              doneAfter: '2024-01-09T00:00:00+01:00',
              doneBefore: '2024-01-09T23:59:59+01:00',
              assignedTo: null,
              previous: null,
              next: null,
              tour: null
            },
            '/api/tasks/736': {
              '@context': '/api/contexts/Task',
              '@id': '/api/tasks/736',
              '@type': 'Task',
              id: 736,
              type: 'DROPOFF',
              status: 'TODO',
              group: {
                '@id': '/api/task_groups/23',
                '@type': 'TaskGroup',
                id: 23,
                name: 'un autre groupe',
                tags: []
              },
              after: '2024-01-09T00:00:00+01:00',
              before: '2024-01-09T23:59:59+01:00',
              isAssigned: true,
              doneAfter: '2024-01-09T00:00:00+01:00',
              doneBefore: '2024-01-09T23:59:59+01:00',
              assignedTo: 'admin',
              previous: null,
              next: null,
              tour: null
            },
          }
        },
        taskLists: {
          ids: [
            'admin'
          ],
          entities: {
            admin: {
              '@context': '/api/contexts/TaskList',
              '@id': '/api/task_lists/112',
              '@type': 'TaskList',
              distance: 8824,
              duration: 2673,
              polyline: 'kufiHiofMMKQCU?qAy@QOKGGEsA_A}@m@sA}@}@m@a@YIm@[JMYCQVeBXoBBQEA}@_@cAc@KEmDmAKESGqAc@eA]UEECmBKA?K?QAyCQyDYWDAEAQCSWFwCs@}Bo@OEaAWKC_Cm@GAYKSGEYi@mDIc@O{@E[YaBSgAEWIc@Ig@SiAKi@e@mCMw@UuAIg@EUOw@Ga@_@{BQeA]qBG[]uBUqA?EI_@YcBEWgAuGGa@Ie@W{AIe@eAiG?CCK?CAGOw@ESWwAEUAMoAkHEYAGGYKk@AI_AsFIi@y@aFIe@G[W{Ak@aDKo@AG_AoFyAqIKm@CSESO_AUK}DmBGEe@i@MSSb@OM{CkBiC}AKGu@c@e@YWOUSEECCoAkAw@u@aA{@WCkAeA_Ay@q@m@WI[I}Ai@Id@G`@cAi@UEO@q@Ck@Uk@C[@eAg@y@EM?C?B?L?x@DdAf@ZAj@Bj@Tp@BNATDbAh@Fa@He@|Ah@ZHVHp@l@~@x@jAdAVB`Az@v@t@nAjABBDDTRVNd@Xt@b@JFhC|AzCjBNLNPb@f@FNbAlCTj@HR@FjBbFBHVp@rCzHJAZ?Jn@j@`DVzAFZHd@x@`FHh@~@rF@HJj@Cb@tAjI^nBBPN`AFZxAjIRvA??xA`Jf@fCTrAF^VrADZDPV~A@BN`AHEP@rJn@F??NAnI?\\?VE|G?PH@vC`@bAJPBFBb@PbBn@ZJNFHDxBv@FBj@TJD~@^HBFBpBr@JDl@Vb@Rh@RNFp@XFBFB~B~@XRd@R^NFBpElBn@VdBr@v@bA|E`HBn@B`@V`@^T\\RZTLHLHrA`AFBNJSPoAzFAj@CA??',
              date: '2024-01-09',
              username: 'admin',
              createdAt: '2024-01-08T15:01:12+01:00',
              updatedAt: '2024-01-09T11:01:58+01:00',
              itemIds: [
                '/api/tasks/729',
                '/api/tasks/730',
                '/api/tasks/731',
                '/api/tasks/727'
              ]
            }
          }
        },
        tours: {
          ids: [
            '/api/tours/111',
            '/api/tours/114'
          ],
          entities: {
            '/api/tours/111': {
              '@context': '/api/contexts/Tour',
              '@id': '/api/tours/111',
              '@type': 'Tour',
              distance: 8824,
              duration: 2673,
              polyline: 'kufiHiofMMKQCU?qAy@QOKGGEsA_A}@m@sA}@}@m@a@YIm@[JMYCQVeBXoBBQEA}@_@cAc@KEmDmAKESGqAc@eA]UEECmBKA?K?QAyCQyDYWDAEAQCSWFwCs@}Bo@OEaAWKC_Cm@GAYKSGEYi@mDIc@O{@E[YaBSgAEWIc@Ig@SiAKi@e@mCMw@UuAIg@EUOw@Ga@_@{BQeA]qBG[]uBUqA?EI_@YcBEWgAuGGa@Ie@W{AIe@eAiG?CCK?CAGOw@ESWwAEUAMoAkHEYAGGYKk@AI_AsFIi@y@aFIe@G[W{Ak@aDKo@AG_AoFyAqIKm@CSESO_AUK}DmBGEe@i@MSSb@OM{CkBiC}AKGu@c@e@YWOUSEECCoAkAw@u@aA{@WCkAeA_Ay@q@m@WI[I}Ai@Id@G`@cAi@UEO@q@Ck@Uk@C[@eAg@y@EM?C?B?L?x@DdAf@ZAj@Bj@Tp@BNATDbAh@Fa@He@|Ah@ZHVHp@l@~@x@jAdAVB`Az@v@t@nAjABBDDTRVNd@Xt@b@JFhC|AzCjBNLNPb@f@FNbAlCTj@HR@FjBbFBHVp@rCzHJAZ?Jn@j@`DVzAFZHd@x@`FHh@~@rF@HJj@Cb@tAjI^nBBPN`AFZxAjIRvA??xA`Jf@fCTrAF^VrADZDPV~A@BN`AHEP@rJn@F??NAnI?\\?VE|G?PH@vC`@bAJPBFBb@PbBn@ZJNFHDxBv@FBj@TJD~@^HBFBpBr@JDl@Vb@Rh@RNFp@XFBFB~B~@XRd@R^NFBpElBn@VdBr@v@bA|E`HBn@B`@V`@^T\\RZTLHLHrA`AFBNJSPoAzFAj@CA??',
              date: '2024-01-09',
              createdAt: '2024-01-08T14:34:25+01:00',
              updatedAt: '2024-01-08T17:59:50+01:00',
              name: 'tour 1',
              itemIds: [
                '/api/tasks/729',
                '/api/tasks/730',
                '/api/tasks/731',
                '/api/tasks/727'
              ]
            },
            '/api/tours/114': {
              '@context': '/api/contexts/Tour',
              '@id': '/api/tours/114',
              '@type': 'Tour',
              name: 'ma tournée',
              items: [
                {
                  '@id': '/api/tasks/733',
                  '@type': 'Task',
                  id: 733,
                  type: 'DROPOFF',
                  status: 'TODO',
                  after: '2024-01-09T00:00:00+01:00',
                  before: '2024-01-09T23:59:59+01:00',
                  isAssigned: false,
                  orgName: '',
                  images: [],
                  doneAfter: '2024-01-09T00:00:00+01:00',
                  doneBefore: '2024-01-09T23:59:59+01:00',
                  assignedTo: null,
                  previous: null,
                  next: null,
                  packages: [],
                  tour: {
                    '@id': '/api/tours/114',
                    name: 'ma tournée',
                    position: 0
                  },
                  position: 0
                },
                {
                  '@id': '/api/tasks/732',
                  '@type': 'Task',
                  id: 732,
                  type: 'DROPOFF',
                  status: 'TODO',
                  after: '2024-01-09T00:00:00+01:00',
                  before: '2024-01-09T23:59:59+01:00',
                  isAssigned: false,
                  orgName: '',
                  images: [],
                  doneAfter: '2024-01-09T00:00:00+01:00',
                  doneBefore: '2024-01-09T23:59:59+01:00',
                  assignedTo: null,
                  previous: null,
                  next: null,
                  packages: [],
                  tour: {
                    '@id': '/api/tours/114',
                    name: 'ma tournée',
                    position: 1
                  },
                  position: 1
                }
              ],
              distance: 4429,
              duration: 1343,
              polyline: 'ovkiHejmMB?L?x@DdAf@ZAj@Bj@Tp@BNATDbAh@Fa@He@|Ah@ZHVHp@l@~@x@jAdAVB`Az@v@t@nAjABBDDTRVNd@Xt@b@JFhC|AzCjBNLNPb@f@FNbAlCTj@HR@FjBbFBHVp@rCzHJAZ?Jn@j@`DVzAFZHd@x@`FHh@~@rF@HJj@Cb@tAjI^nBBPN`AFZxAjIRvA??xA`Jf@fCTrAF^VrADZDPV~A@BN`AHEP@rJn@F??NAnI?\\?VE|G?PH@vC`@bAJPBFBb@PbBn@ZJNFHDxBv@FBj@TJD~@^HBFBpBr@JDl@Vb@Rh@RNFp@XFBFB~B~@XRd@R^NFBpElBn@VdBr@v@bA|E`HBn@B`@V`@^T\\RZTLHLHrA`AFBNJSPoAzFAj@CA',
              date: '2024-01-09',
              createdAt: '2024-01-09T12:46:23+01:00',
              updatedAt: '2024-01-09T12:46:47+01:00',
              itemIds: [
                '/api/tasks/733',
                '/api/tasks/732'
              ],
              tasks: [
                {
                  '@context': '/api/contexts/Task',
                  '@id': '/api/tasks/733',
                  '@type': 'Task',
                  id: 733,
                  type: 'DROPOFF',
                  status: 'TODO',
                  group: null,
                  after: '2024-01-09T00:00:00+01:00',
                  before: '2024-01-09T23:59:59+01:00',
                  isAssigned: false,
                  orgName: '',
                  images: [],
                  doneAfter: '2024-01-09T00:00:00+01:00',
                  doneBefore: '2024-01-09T23:59:59+01:00',
                  assignedTo: null,
                  previous: null,
                  next: null,
                  packages: [],
                  tour: null
                },
                {
                  '@context': '/api/contexts/Task',
                  '@id': '/api/tasks/732',
                  '@type': 'Task',
                  id: 732,
                  type: 'DROPOFF',
                  status: 'TODO',
                  group: null,
                  after: '2024-01-09T00:00:00+01:00',
                  before: '2024-01-09T23:59:59+01:00',
                  isAssigned: false,
                  orgName: '',
                  images: [],
                  doneAfter: '2024-01-09T00:00:00+01:00',
                  doneBefore: '2024-01-09T23:59:59+01:00',
                  assignedTo: null,
                  previous: null,
                  next: null,
                  packages: [],
                  tour: {
                    '@context': '/api/contexts/Tour',
                    '@id': '/api/tours/114',
                    '@type': 'Tour',
                    name: 'ma tournée',
                    items: [],
                    distance: 0,
                    duration: 0,
                    polyline: '',
                    date: '2024-01-09',
                    createdAt: '2024-01-09T12:46:23+01:00',
                    updatedAt: '2024-01-09T12:46:23+01:00',
                    itemIds: [],
                    position: 0
                  }
                }
              ]
            }
          }
        }
      },
      ui: {
        taskListsLoading: false,
        areToursDroppable: false,
        currentTask: null
      }
    },
    config: {
      couriersList: [
        {
          username: 'admin'
        },
        {
          username: 'bot_1'
        },
        {
          username: 'bot_10'
        },
      ],
     },
  }