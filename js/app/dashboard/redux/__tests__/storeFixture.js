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
            '/api/tasks/736',
            '/api/tasks/737',
            '/api/tasks/738'
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
              packages: []
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
              packages: []
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
              next: null
            },
            '/api/tasks/737': {
              '@context': '/api/contexts/Task',
              '@id': '/api/tasks/737',
              '@type': 'Task',
              id: 737,
              type: 'DROPOFF',
              status: 'TODO',
              group: null,
              after: '2024-01-09T00:00:00+01:00',
              before: '2024-01-09T23:59:59+01:00',
              isAssigned: true,
              doneAfter: '2024-01-09T00:00:00+01:00',
              doneBefore: '2024-01-09T23:59:59+01:00',
              assignedTo: 'admin',
              previous: null,
              next: '/api/tasks/738'
            },
            '/api/tasks/738': {
              '@context': '/api/contexts/Task',
              '@id': '/api/tasks/738',
              '@type': 'Task',
              id: 738,
              type: 'DROPOFF',
              status: 'TODO',
              group: null,
              after: '2024-01-09T00:00:00+01:00',
              before: '2024-01-09T23:59:59+01:00',
              isAssigned: true,
              doneAfter: '2024-01-09T00:00:00+01:00',
              doneBefore: '2024-01-09T23:59:59+01:00',
              assignedTo: 'admin',
              previous: '/api/tasks/737',
              next: null
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
              items: [
                '/api/tours/111'
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
              items: [
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
              distance: 4429,
              duration: 1343,
              polyline: 'ovkiHejmMB?L?x@DdAf@ZAj@Bj@Tp@BNATDbAh@Fa@He@|Ah@ZHVHp@l@~@x@jAdAVB`Az@v@t@nAjABBDDTRVNd@Xt@b@JFhC|AzCjBNLNPb@f@FNbAlCTj@HR@FjBbFBHVp@rCzHJAZ?Jn@j@`DVzAFZHd@x@`FHh@~@rF@HJj@Cb@tAjI^nBBPN`AFZxAjIRvA??xA`Jf@fCTrAF^VrADZDPV~A@BN`AHEP@rJn@F??NAnI?\\?VE|G?PH@vC`@bAJPBFBb@PbBn@ZJNFHDxBv@FBj@TJD~@^HBFBpBr@JDl@Vb@Rh@RNFp@XFBFB~B~@XRd@R^NFBpElBn@VdBr@v@bA|E`HBn@B`@V`@^T\\RZTLHLHrA`AFBNJSPoAzFAj@CA',
              date: '2024-01-09',
              createdAt: '2024-01-09T12:46:23+01:00',
              updatedAt: '2024-01-09T12:46:47+01:00',
              items: [
                '/api/tasks/733',
                '/api/tasks/732'
              ],
            }
          }
        }
      },
      ui: {
        unassignedTasksIdsOrder: [
          '/api/tasks/728',
          '/api/tasks/732',
          '/api/tasks/733',
          '/api/tasks/734',
          '/api/tasks/735',
          '/api/tasks/736',
          '/api/tasks/737',
          '/api/tasks/738'
        ]
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